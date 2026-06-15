<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SavesSubCpmks;
use App\Models\Course;
use App\Models\Cpmk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CpmkController extends Controller
{
    use SavesSubCpmks;

    // ─── CONST ───────────────────────────────────────────────────────────────
    const TOTAL_MEETINGS = 16;

    /**
     * Pastikan mata kuliah (pemilik CPMK) berada di prodi/jurusan user yang login.
     * Cegah kaprodi memodifikasi CPMK milik prodi lain (IDOR).
     */
    private function authorizeCourse(?Course $course): void
    {
        abort_if($course === null, 404);

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
     * Recalculate meeting_start / meeting_end for all CPMKs of a course,
     * ordered by their id (creation order).
     */
    private function recalculateMeetings(int $courseId): void
    {
        $cpmks = Cpmk::where('course_id', $courseId)->orderBy('id')->get();
        $current = 1;
        foreach ($cpmks as $cpmk) {
            $count = (int) round(($cpmk->percentage / 100) * self::TOTAL_MEETINGS);
            $count = max($count, 1);
            $cpmk->meeting_start = $current;
            $cpmk->meeting_end = $current + $count - 1;
            $cpmk->save();
            $current += $count;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Course $course)
    {
        // Form sekarang berupa modal di halaman detail mata kuliah.
        return redirect()->route('courses.show', $course);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:obe_mata_kuliah,id',
            'cpl_id' => 'required|exists:obe_cpl,id',
            'code' => 'required|string',
            'description' => 'required|string',
            'lecturer_id' => 'nullable|exists:obe_pengguna,id',
            'percentage' => 'required|numeric|min:1|max:100',
            'subcpmks' => 'nullable|array',
            'subcpmks.*.description' => 'nullable|string',
            'subcpmks.*.percentage' => 'nullable|numeric|min:1|max:100',
            'subcpmks.*.meetings' => 'nullable|integer|min:1|max:16',
            'subcpmks.*.indicators' => 'nullable|array',
            'subcpmks.*.indicators.*.description' => 'nullable|string',
            'subcpmks.*.indicators.*.percentage' => 'nullable|numeric|min:1|max:100',
        ]);

        $this->authorizeCourse(Course::find($validated['course_id']));

        // Validasi: total bobot semua CPMK dalam MK ini tidak boleh melebihi 100%
        $existingTotal = Cpmk::where('course_id', $validated['course_id'])->sum('percentage');
        $newTotal = $existingTotal + (float) $validated['percentage'];
        if (round($newTotal, 2) > 100.0) {
            $remaining = round(100 - $existingTotal, 2);

            return back()->withInput()->withErrors([
                'percentage' => "Total bobot CPMK akan menjadi {$newTotal}% (melebihi 100%). Sisa bobot yang tersedia: {$remaining}%.",
            ]);
        }

        // Create CPMK
        $cpmk = Cpmk::create([
            'course_id' => $validated['course_id'],
            'cpl_id' => $validated['cpl_id'],
            'code' => $validated['code'],
            'description' => $validated['description'],
            'lecturer_id' => $validated['lecturer_id'] ?? null,
            'percentage' => $validated['percentage'],
        ]);

        try {
            $this->saveSubCpmks($cpmk, $validated['subcpmks'] ?? []);
        } catch (\Throwable $e) {
            $cpmk->delete();

            return back()->withInput()->withErrors(['subcpmks' => 'Gagal menyimpan Sub-CPMK: '.$e->getMessage()]);
        }

        // Recalculate meeting ranges for all CPMKs of this course
        $this->recalculateMeetings((int) $validated['course_id']);

        return redirect()->route('courses.show', $validated['course_id'])->with('success', 'CPMK berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cpmk $cpmk)
    {
        // Form sekarang berupa modal di halaman detail mata kuliah.
        return redirect()->route('courses.show', $cpmk->course_id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cpmk $cpmk)
    {
        $this->authorizeCourse($cpmk->course);

        $validated = $request->validate([
            'cpl_id' => 'required|exists:obe_cpl,id',
            'code' => 'required|string',
            'description' => 'required|string',
            'lecturer_id' => 'nullable|exists:obe_pengguna,id',
            'percentage' => 'required|numeric|min:1|max:100',
            'subcpmks' => 'nullable|array',
            'subcpmks.*.description' => 'nullable|string',
            'subcpmks.*.percentage' => 'nullable|numeric|min:1|max:100',
            'subcpmks.*.meetings' => 'nullable|integer|min:1|max:16',
            'subcpmks.*.indicators' => 'nullable|array',
            'subcpmks.*.indicators.*.description' => 'nullable|string',
            'subcpmks.*.indicators.*.percentage' => 'nullable|numeric|min:1|max:100',
        ]);

        // Validasi: total bobot semua CPMK lain dalam MK ini + bobot baru tidak boleh melebihi 100%
        $existingTotal = Cpmk::where('course_id', $cpmk->course_id)
            ->where('id', '!=', $cpmk->id)
            ->sum('percentage');
        $newTotal = $existingTotal + (float) $validated['percentage'];
        if (round($newTotal, 2) > 100.0) {
            $remaining = round(100 - $existingTotal, 2);

            return back()->withInput()->withErrors([
                'percentage' => "Total bobot CPMK akan menjadi {$newTotal}% (melebihi 100%). Sisa bobot yang tersedia: {$remaining}%.",
            ]);
        }

        // Update CPMK
        $cpmk->update([
            'cpl_id' => $validated['cpl_id'],
            'code' => $validated['code'],
            'description' => $validated['description'],
            'lecturer_id' => $validated['lecturer_id'] ?? null,
            'percentage' => $validated['percentage'],
        ]);

        $cpmk->indicators()->delete();
        $cpmk->subCpmks()->delete();

        try {
            $this->saveSubCpmks($cpmk, $validated['subcpmks'] ?? []);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['subcpmks' => 'Gagal menyimpan Sub-CPMK: '.$e->getMessage()]);
        }

        // Recalculate meeting ranges for all CPMKs of this course
        $this->recalculateMeetings($cpmk->course_id);

        return redirect()->route('courses.show', $cpmk->course_id)->with('success', 'CPMK berhasil diperbarui.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Cpmk $cpmk)
    {
        $this->authorizeCourse($cpmk->course);

        $cpmk->load(['course', 'cpl', 'lecturer', 'subCpmks.indicators']);

        return view('kaprodi.cpmks.show', compact('cpmk'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cpmk $cpmk)
    {
        $this->authorizeCourse($cpmk->course);

        $courseId = $cpmk->course_id;
        $cpmk->delete();

        // Recalculate meeting ranges after deletion
        $this->recalculateMeetings($courseId);

        return redirect()->route('courses.show', $courseId)->with('success', 'CPMK berhasil dihapus.');
    }
}
