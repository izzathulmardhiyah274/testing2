<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SavesSubCpmks;
use App\Models\Course;
use App\Models\Cpl;
use App\Models\Cpmk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    use SavesSubCpmks;

    /**
     * Pastikan mata kuliah ini milik prodi/jurusan user yang login (cegah IDOR lintas prodi).
     * Saat user tidak punya konteks prodi/jurusan (mis. superadmin), tidak diblokir.
     */
    private function authorizeCourse(Course $course): void
    {
        $auth = Auth::user();
        $prodiId = $auth?->activeProdiId();

        if ($prodiId !== null) {
            abort_unless((int) $course->program_studi_id === $prodiId, 403, 'Mata kuliah ini milik program studi lain.');

            return;
        }

        if ($auth && $auth->role === 'admin_jurusan' && $auth->jurusan_id) {
            abort_unless((int) $course->jurusan_id === (int) $auth->jurusan_id, 403, 'Mata kuliah ini milik jurusan lain.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $semester = $request->input('semester');
        $auth = Auth::user();

        $query = Course::with(['users', 'cpmks.lecturer', 'cpmks.cpl']);

        if ($semester) {
            $query->where('semester', $semester);
        }

        // ── Scope per role yang sedang login ──────────────────────────────
        if ($auth) {
            if ($auth->role === 'kaprodi') {
                $prodiId = $auth->activeProdiId();
                $jurusanId = $auth->jurusan_id;

                if ($prodiId) {
                    $query->where('program_studi_id', $prodiId);
                } elseif ($jurusanId) {
                    $query->where('jurusan_id', $jurusanId);
                }
            } elseif ($auth->role === 'admin_jurusan' && $auth->jurusan_id) {
                $query->where('jurusan_id', $auth->jurusan_id);
            }
        }

        $courses = $query->orderBy('semester')
            ->orderBy('code')
            ->get();

        $allCourses = Course::orderBy('code')->get(['id', 'code', 'name']);

        return view('kaprodi.courses.index', compact('courses', 'allCourses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $courses = Course::orderBy('code')->get();

        $auth = Auth::user();
        $prodiId = $auth?->activeProdiId();

        // Pada halaman create MK baru, belum ada MK yang terbentuk,
        // jadi CPL yang ditampilkan adalah semua CPL prodi (untuk referensi).
        // CPL akan diikat ke MK nanti setelah MK disimpan melalui halaman show.
        $cplQuery = Cpl::orderBy('code');
        if ($prodiId) {
            $cplQuery->where('program_studi_id', $prodiId);
        }
        $cpls = $cplQuery->get();

        $lecturerQuery = \App\Models\User::where('role', 'dosen')->orderBy('name');
        if ($auth && $auth->role === 'admin_jurusan' && $auth->jurusan_id) {
            $lecturerQuery->where('jurusan_id', $auth->jurusan_id);
        }
        $lecturers = $lecturerQuery->get();

        return view('kaprodi.courses.create', compact('courses', 'cpls', 'lecturers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:obe_mata_kuliah,code',
            'name' => 'required|string',
            'sks' => 'required|integer|min:1',
            'semester' => 'required|integer|min:1|max:8',
            'wajib_pilihan' => 'required|in:W,P',
            'prerequisite_course_id' => 'nullable|exists:obe_mata_kuliah,id',
            'cpl_ids' => 'required|array|min:1',
            'cpl_ids.*' => 'integer|exists:obe_cpl,id',
        ], [
            'cpl_ids.required' => 'Pilih minimal 1 CPL yang dibebankan ke mata kuliah ini.',
            'cpl_ids.min' => 'Pilih minimal 1 CPL yang dibebankan ke mata kuliah ini.',
        ]);

        // CPL yang dibebankan ke MK — dipakai untuk membatasi cpl_id tiap CPMK
        $selectedCplIds = array_map('intval', $validated['cpl_ids']);

        DB::beginTransaction();
        try {
            $auth = Auth::user();
            $course = Course::create([
                'jurusan_id' => $auth?->jurusan_id ?? null,
                'program_studi_id' => $auth?->activeProdiId() ?? null,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'sks' => $validated['sks'],
                'semester' => $validated['semester'],
                'wajib_pilihan' => $validated['wajib_pilihan'],
                'prerequisite_course_id' => $validated['prerequisite_course_id'] ?? null,
            ]);

            // Ikat CPL ke MK (tabel pivot obe_mata_kuliah_cpl).
            $course->cpls()->sync($selectedCplIds);

            // Save CPMKs from hidden inputs (added via modal)
            // Catatan: pada create MK baru, CPMK yang ditambahkan di sini
            // tidak melewati validasi filter CPL-MK karena MK-nya baru saja dibuat.
            // Validasi penuh (CPL harus terikat ke MK) berlaku di CpmkController@store/update.
            $cpmksInput = $request->input('cpmks', []);
            $meeting = 1;
            $total = 16;
            foreach ($cpmksInput as $cpmkData) {
                if (empty($cpmkData['code']) || empty($cpmkData['description'])) {
                    continue;
                }

                $pct = (float) ($cpmkData['percentage'] ?? 0);
                $count = max(1, (int) round(($pct / 100) * $total));

                // Hanya terima cpl_id yang termasuk CPL yang dibebankan ke MK ini.
                $cpmkCplId = (int) ($cpmkData['cpl_id'] ?? 0);
                $cpmkCplId = in_array($cpmkCplId, $selectedCplIds, true) ? $cpmkCplId : null;

                $cpmk = Cpmk::create([
                    'course_id' => $course->id,
                    'cpl_id' => $cpmkCplId,
                    'code' => $cpmkData['code'],
                    'description' => $cpmkData['description'],
                    'percentage' => $pct,
                    'meeting_start' => $meeting,
                    'meeting_end' => $meeting + $count - 1,
                ]);
                $meeting += $count;

                $this->saveSubCpmks($cpmk, $cpmkData['subcpmks'] ?? []);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan: '.$e->getMessage());
        }

        return redirect()->route('courses.index')
            ->with('success', 'Mata Kuliah berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     * PERBAIKAN: $cpls sekarang hanya berisi CPL yang sudah diikat ke MK ini
     * melalui tabel obe_mata_kuliah_cpl, bukan semua CPL prodi.
     * Ini memastikan dropdown CPMK hanya menampilkan pilihan yang valid.
     */
    public function show(Course $course)
    {
        $this->authorizeCourse($course);

        $course->load(['cpls', 'prerequisite', 'cpmks.cpl', 'cpmks.lecturer', 'cpmks.indicators', 'cpmks.subCpmks.indicators']);

        $auth = Auth::user();

        // PERBAIKAN UTAMA: ambil CPL hanya dari yang sudah diikat ke MK ini.
        // Relasi cpls() di model Course sudah via obe_mata_kuliah_cpl.
        $cpls = $course->cpls()->orderBy('code')->get();

        // Semua CPL prodi sebagai opsi yang dapat dicentang pada form edit MK,
        // sehingga kaprodi bisa menambah/melepas CPL yang dibebankan ke MK ini.
        $allCplQuery = Cpl::orderBy('code');
        if ($course->program_studi_id) {
            $allCplQuery->where('program_studi_id', $course->program_studi_id);
        }
        $allCpls = $allCplQuery->get();

        $lecturerQuery = \App\Models\User::where('role', 'dosen')->orderBy('name');
        if ($auth && $auth->role === 'admin_jurusan' && $auth->jurusan_id) {
            $lecturerQuery->where('jurusan_id', $auth->jurusan_id);
        }
        $lecturers = $lecturerQuery->get();

        $prereqCourses = Course::where('id', '!=', $course->id)->orderBy('code')->get(['id', 'code', 'name']);

        return view('kaprodi.courses.show', compact('course', 'cpls', 'allCpls', 'lecturers', 'prereqCourses'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course)
    {
        return redirect()->route('courses.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course)
    {
        $this->authorizeCourse($course);

        $validated = $request->validate([
            'code' => 'required|string|unique:obe_mata_kuliah,code,'.$course->id,
            'name' => 'required|string',
            'sks' => 'required|integer|min:1',
            'semester' => 'required|integer|min:1|max:8',
            'wajib_pilihan' => 'required|in:W,P',
            'prerequisite_course_id' => 'nullable|exists:obe_mata_kuliah,id',
            'cpl_ids' => 'required|array|min:1',
            'cpl_ids.*' => 'integer|exists:obe_cpl,id',
        ], [
            'cpl_ids.required' => 'Pilih minimal 1 CPL yang dibebankan ke mata kuliah ini.',
            'cpl_ids.min' => 'Pilih minimal 1 CPL yang dibebankan ke mata kuliah ini.',
        ]);

        $selectedCplIds = array_map('intval', $validated['cpl_ids']);

        // Cegah pemilihan CPL dari program studi lain (isolasi prodi).
        if ($course->program_studi_id) {
            $allowedCplIds = Cpl::where('program_studi_id', $course->program_studi_id)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            abort_unless(empty(array_diff($selectedCplIds, $allowedCplIds)), 403, 'CPL dipilih dari program studi lain.');
        }

        // Auto-cleanup: CPL yang tidak lagi dicentang dilepas dari CPMK yang
        // memakainya (cpl_id dikosongkan) agar tidak ada referensi cpl_id yatim.
        $detachedCount = DB::transaction(function () use ($course, $validated, $selectedCplIds) {
            $affected = $course->cpmks()
                ->whereNotNull('cpl_id')
                ->whereNotIn('cpl_id', $selectedCplIds)
                ->update(['cpl_id' => null]);

            $course->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'sks' => $validated['sks'],
                'semester' => $validated['semester'],
                'wajib_pilihan' => $validated['wajib_pilihan'],
                'prerequisite_course_id' => $validated['prerequisite_course_id'] ?? null,
            ]);

            $course->cpls()->sync($selectedCplIds);

            return $affected;
        });

        $message = 'Mata Kuliah berhasil diupdate.';
        if ($detachedCount > 0) {
            $message .= " {$detachedCount} CPMK yang sebelumnya memakai CPL yang dilepas kini tidak terhubung ke CPL — silakan tetapkan ulang CPL-nya.";
        }

        return redirect()->route('courses.show', $course)->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        $this->authorizeCourse($course);

        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Mata Kuliah berhasil dihapus.');
    }
}
