<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomCpmk;
use App\Models\Cpl;
use App\Models\User;
use App\Notifications\CpmkStatusChanged;
use App\Services\CplAchievementService;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KaprodiController extends Controller
{
    use \App\Http\Controllers\Concerns\BuildsGradeReport;

    /* ── Helper: scope query kelas berdasarkan prodi kaprodi yang login ── */

    /**
     * Scope classroom query agar hanya menampilkan kelas milik prodi kaprodi.
     * Tidak ada fallback ke jurusan — data NULL program_studi_id tidak ikut tampil.
     * Ini memastikan kaprodi S1 TE tidak melihat data TI meskipun satu jurusan.
     */
    private function applyKaprodiScope($query, User $auth): void
    {
        $prodiId = $auth->activeProdiId();
        $jurusanId = $auth->jurusan_id;

        if ($prodiId) {
            // Scope KETAT: hanya kelas yang course-nya eksplisit milik prodi ini
            $query->whereHas('course', fn ($cq) => $cq->where('program_studi_id', $prodiId)
            );
        } elseif ($jurusanId) {
            // Kaprodi belum dikonfigurasi prodinya → fallback ke jurusan
            // Ini sebaiknya tidak terjadi jika admin sudah set prodi kaprodi dengan benar.
            $query->where(function ($q) use ($jurusanId) {
                $q->whereHas('lecturer', fn ($lq) => $lq->where('jurusan_id', $jurusanId))
                    ->orWhereHas('cpmkLecturers', fn ($lq) => $lq->where('jurusan_id', $jurusanId));
            });
        }
    }

    /* ── Helper: Query builder kelas ──────────────────────────────────── */
    private function classroomQuery(Request $request, bool $archived = false)
    {
        $period = Classroom::currentPeriod();
        $activePeriod = $period;

        $years = Classroom::select('academic_year')
            ->whereNotNull('academic_year')
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        $query = Classroom::with(['course.cpmks', 'cpmkLecturers', 'cpmks'])
            ->where('is_archived', $archived);

        $filterYear = $request->input('academic_year', $archived ? null : $activePeriod['academic_year']);
        if ($filterYear) {
            $query->where('academic_year', $filterYear);
        }

        if ($request->filled('period_type')) {
            $query->where('period_type', $request->period_type);
        }

        if ($request->filled('semester')) {
            $query->whereHas('course', fn ($q) => $q->where('semester', $request->semester));
        }

        // ── Scope per role yang sedang login ──────────────────────────────
        $auth = Auth::user();

        if ($auth && $auth->role === 'admin_jurusan' && $auth->jurusan_id) {
            $jurusanId = $auth->jurusan_id;
            $query->where(function ($q) use ($jurusanId) {
                $q->whereHas('lecturer', fn ($lq) => $lq->where('jurusan_id', $jurusanId))
                    ->orWhereHas('cpmkLecturers', fn ($lq) => $lq->where('jurusan_id', $jurusanId));
            });
        } elseif ($auth && $auth->role === 'kaprodi') {
            $this->applyKaprodiScope($query, $auth);
        }

        $classrooms = $query->orderBy('period_type')->orderBy('name')->get();

        return compact('classrooms', 'years', 'filterYear', 'activePeriod');
    }

    /* ── Laporan: Index (CPL-centris) ──────────────────────────────────── */
    /**
     * Tab "Laporan Nilai (per Kelas)" kini menampilkan daftar CPL. Tiap CPL
     * bisa dibuka untuk melihat CPMK pendukungnya dan mengatur bobot kontribusi
     * tiap CPMK terhadap CPL tersebut (sumbu "ke atas").
     */
    public function laporanIndex(Request $request)
    {
        $prodiId = Auth::user()?->activeProdiId();

        $cpls = Cpl::query()
            ->when($prodiId, fn ($q) => $q->where('program_studi_id', $prodiId))
            ->withCount('cpmks')
            ->orderBy('code')
            ->get();

        return view('kaprodi.laporan.index', compact('cpls'));
    }

    /* ── Laporan: Detail CPL + atur bobot CPMK→CPL ─────────────────────── */
    public function laporanCplShow(Cpl $cpl)
    {
        $this->authorizeCpl($cpl);

        $cpmks = $cpl->cpmks()
            ->with('course:id,code,name,sks')
            ->orderBy('code')
            ->get();

        $effectiveWeights = $this->effectiveCplWeights($cpmks);

        return view('kaprodi.laporan.cpl-show', compact('cpl', 'cpmks', 'effectiveWeights'));
    }

    public function storeCplWeights(Request $request, Cpl $cpl)
    {
        $this->authorizeCpl($cpl);

        $validated = $request->validate([
            'weights' => 'required|array',
            'weights.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $ownedIds = $cpl->cpmks()->pluck('id')->all();

        foreach ($validated['weights'] as $cpmkId => $value) {
            if (! in_array((int) $cpmkId, $ownedIds, true)) {
                continue;
            }

            $cpl->cpmks()->whereKey($cpmkId)->update([
                'cpl_weight' => $value === null || $value === '' ? null : (float) $value,
            ]);
        }

        return redirect()->route('kaprodi.laporan.cpl.show', $cpl)
            ->with('success', 'Bobot kontribusi CPMK terhadap CPL berhasil disimpan.');
    }

    /**
     * Bobot efektif tiap CPMK terhadap CPL: pakai cpl_weight bila ada,
     * sisanya dibagi rata otomatis. Total selalu 100%.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Cpmk>  $cpmks
     * @return array<int, float> [cpmk_id => bobot%]
     */
    private function effectiveCplWeights($cpmks): array
    {
        $manual = $cpmks->filter(fn ($c) => $c->cpl_weight !== null);
        $auto = $cpmks->filter(fn ($c) => $c->cpl_weight === null);

        $manualTotal = (float) $manual->sum(fn ($c) => (float) $c->cpl_weight);
        $remaining = max(0.0, 100.0 - $manualTotal);
        $autoWeights = GradeService::distributeAutoWeights($auto->count(), $remaining);

        $out = [];
        foreach ($manual as $c) {
            $out[$c->id] = (float) $c->cpl_weight;
        }
        $i = 0;
        foreach ($auto as $c) {
            $out[$c->id] = $autoWeights[$i++] ?? 0.0;
        }

        return $out;
    }

    /**
     * Pastikan CPL milik prodi user (cegah akses lintas prodi).
     */
    private function authorizeCpl(Cpl $cpl): void
    {
        $prodiId = Auth::user()?->activeProdiId();

        if ($prodiId !== null && (int) $cpl->program_studi_id !== $prodiId) {
            abort(403, 'CPL ini milik program studi lain.');
        }
    }

    /* ── Laporan: Show (detail nilai) ──────────────────────────────────── */
    public function laporanShow(Classroom $classroom)
    {
        $course = $classroom->course;

        $cpmks = $course
            ? $course->cpmks()->with([
                'cpl',
                'indicators' => fn ($q) => $q->orderBy('id'),
                'indicators.assessments' => fn ($q) => $q->where('classroom_id', $classroom->id)->orderBy('id'),
                'indicators.assessments.scores',
                'indicators.classroomWeights' => fn ($q) => $q->where('classroom_id', $classroom->id),
                'subCpmks' => fn ($q) => $q->orderBy('id'),
                'subCpmks.indicators' => fn ($q) => $q->orderBy('id'),
                'subCpmks.indicators.assessments' => fn ($q) => $q->where('classroom_id', $classroom->id)->orderBy('id'),
                'subCpmks.indicators.assessments.scores',
                'subCpmks.indicators.classroomWeights' => fn ($q) => $q->where('classroom_id', $classroom->id),
            ])->orderBy('id')->get()
            : collect();

        $students = $classroom->students()->orderBy('identity')->get();

        $scoreMap = $this->buildScoreMap($cpmks);

        $rows = $this->buildRows($students, $cpmks, $scoreMap, $classroom->id);
        $cplRows = CplAchievementService::perClassroom($rows, $cpmks);

        return view('kaprodi.laporan.show', compact('classroom', 'course', 'rows', 'cpmks', 'cplRows'));
    }

    /* ── Laporan: Mahasiswa (ketercapaian CPL per mahasiswa) ────────────── */
    public function laporanMahasiswa(Request $request)
    {
        $auth = Auth::user();
        $prodiId = $auth->activeProdiId();

        // CPL hanya dari prodi kaprodi yang sedang login (tanpa fallback NULL)
        $cplQuery = \App\Models\Cpl::orderBy('code');
        if ($prodiId) {
            $cplQuery->where('program_studi_id', $prodiId);
        }
        $cpls = $cplQuery->get();

        $filterAngkatan = $request->input('angkatan');

        // Ambil kelas yang sudah di-scope ke prodi kaprodi
        $classroomQuery = Classroom::with([
            'course.cpmks.cpl',
            'course.cpmks.indicators.assessments.scores', 'course.cpmks.subCpmks.indicators.assessments.scores',
        ]);

        if ($auth->role === 'kaprodi') {
            $this->applyKaprodiScope($classroomQuery, $auth);
        }

        $classrooms = $classroomQuery->get();

        $allStudentIds = $classrooms->flatMap(fn ($c) => $c->students->pluck('id'))->unique();

        $studentQuery = \App\Models\User::whereIn('id', $allStudentIds)
            ->with('profilMahasiswa')
            ->orderBy('identity');

        if ($prodiId) {
            $studentQuery->whereHas('profilMahasiswa', fn ($mq) => $mq->where('program_studi_id', $prodiId)
            );
        }

        $allStudents = $studentQuery->get();

        if ($filterAngkatan) {
            $suffix = substr($filterAngkatan, -2);
            $allStudents = $allStudents->filter(function ($student) use ($suffix) {
                $nim = $student->profilMahasiswa?->nim ?? $student->identity ?? '';

                return substr($nim, 0, 2) === $suffix;
            })->values();
        }

        $angkatanBaseQuery = \App\Models\User::whereIn('id', $allStudentIds)
            ->with('profilMahasiswa');

        if ($prodiId) {
            $angkatanBaseQuery->whereHas('profilMahasiswa', fn ($mq) => $mq->where('program_studi_id', $prodiId)
            );
        }

        $angkatanList = $angkatanBaseQuery->get()
            ->map(function ($s) {
                $nim = $s->profilMahasiswa?->nim ?? $s->identity ?? '';
                $prefix = substr($nim, 0, 2);

                return is_numeric($prefix) ? '20'.$prefix : null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Ketercapaian CPL per mahasiswa: kumpulkan nilai tiap CPMK (yang sudah
        // dinilai) ke CPL pendukungnya. Komponen difilter ke kelas terkait.
        $cplPerStudent = [];
        foreach ($classrooms as $classroom) {
            $course = $classroom->course;
            if (! $course) {
                continue;
            }

            $cpmks = $course->cpmks;

            foreach ($classroom->students as $student) {
                $agg = $this->aggregateClassroom($classroom, $student->id);

                foreach ($cpmks as $ci => $cpmk) {
                    $cplId = $cpmk->cpl?->id;
                    $total = $agg['cpmks'][$ci]['total'] ?? null;
                    if ($cplId && $total !== null) {
                        $cplPerStudent[$student->id][$cplId][] = $total;
                    }
                }
            }
        }

        $studentCplMap = [];
        foreach ($allStudents as $student) {
            $sid = $student->id;
            foreach ($cpls as $cpl) {
                $scores = $cplPerStudent[$sid][$cpl->id] ?? [];
                $studentCplMap[$sid][$cpl->id] = count($scores)
                    ? round(array_sum($scores) / count($scores), 1)
                    : null;
            }
        }

        return view('kaprodi.laporan.mahasiswa', compact(
            'cpls', 'allStudents', 'studentCplMap', 'angkatanList', 'filterAngkatan'
        ));
    }

    /* ── Laporan: Detail Mahasiswa (daftar kelas + nilai per kelas) ────── */
    public function laporanMahasiswaShow(User $student)
    {
        $auth = Auth::user();
        $prodiId = $auth->activeProdiId();

        // Ambil semua kelas yang diikuti mahasiswa ini, scope ke prodi kaprodi
        $classroomQuery = $student->classrooms()->with([
            'course.cpmks.cpl',
            'course.cpmks.indicators.assessments.scores', 'course.cpmks.subCpmks.indicators.assessments.scores',
        ]);

        if ($prodiId) {
            $classroomQuery->whereHas('course', fn ($q) => $q->where('program_studi_id', $prodiId)
            );
        }

        $classrooms = $classroomQuery->orderByDesc('academic_year')->orderBy('name')->get();

        // Hitung nilai per kelas untuk mahasiswa ini
        $classroomResults = [];
        foreach ($classrooms as $classroom) {
            $course = $classroom->course;
            if (! $course) {
                $classroomResults[$classroom->id] = null;

                continue;
            }

            $agg = $this->aggregateClassroom($classroom, $student->id);

            $cpmkResults = [];
            foreach ($course->cpmks as $ci => $cpmk) {
                $cpmkResults[] = [
                    'cpmk' => $cpmk,
                    'score' => $agg['cpmks'][$ci]['total'],
                    'lulus' => $agg['cpmks'][$ci]['lulus'] ?? false,
                ];
            }

            $classroomResults[$classroom->id] = [
                'cpmkResults' => $cpmkResults,
                'finalScore' => $agg['final_score'],
                'finalMutu' => $agg['final_mutu'] ?? GradeService::toMutu(0),
                'finalGrade' => $agg['final_grade'] ?? GradeService::toHuruf(0),
                'anyFailed' => $agg['any_failed'],
            ];
        }

        // CPL summary untuk mahasiswa ini
        $cplQuery = \App\Models\Cpl::orderBy('code');
        if ($prodiId) {
            $cplQuery->where('program_studi_id', $prodiId);
        }
        $cpls = $cplQuery->get();

        $cplScores = [];
        foreach ($classrooms as $classroom) {
            $course = $classroom->course;
            if (! $course) {
                continue;
            }
            foreach ($course->cpmks as $cpmk) {
                $cplId = $cpmk->cpl?->id;
                if (! $cplId) {
                    continue;
                }
                $res = collect($classroomResults[$classroom->id]['cpmkResults'] ?? [])
                    ->firstWhere('cpmk.id', $cpmk->id);
                if ($res && $res['score'] !== null) {
                    $cplScores[$cplId][] = $res['score'];
                }
            }
        }
        $cplSummary = [];
        foreach ($cpls as $cpl) {
            $scores = $cplScores[$cpl->id] ?? [];
            $cplSummary[$cpl->id] = count($scores)
                ? round(array_sum($scores) / count($scores), 1)
                : null;
        }

        return view('kaprodi.laporan.mahasiswa-detail', compact(
            'student', 'classrooms', 'classroomResults', 'cpls', 'cplSummary'
        ));
    }

    /* ── Laporan: Detail Nilai Mahasiswa per Kelas ─────────────────────── */
    public function laporanMahasiswaKelasShow(User $student, Classroom $classroom)
    {
        $auth = Auth::user();
        $prodiId = $auth->activeProdiId();

        // Pastikan mahasiswa terdaftar di kelas ini
        abort_unless($classroom->students()->where('user_id', $student->id)->exists(), 403);

        // Pastikan kelas dalam scope prodi kaprodi
        if ($prodiId) {
            abort_unless(
                optional($classroom->course)->program_studi_id == $prodiId,
                403
            );
        }

        $course = $classroom->course;

        $cpmks = $course
            ? $course->cpmks()->with([
                'cpl',
                'indicators.assessments' => fn ($q) => $q->where('classroom_id', $classroom->id)->orderBy('id'),
                'indicators.assessments.scores',
                'indicators.classroomWeights' => fn ($q) => $q->where('classroom_id', $classroom->id),
                'subCpmks' => fn ($q) => $q->orderBy('id'),
                'subCpmks.indicators' => fn ($q) => $q->orderBy('id'),
                'subCpmks.indicators.assessments' => fn ($q) => $q->where('classroom_id', $classroom->id)->orderBy('id'),
                'subCpmks.indicators.assessments.scores',
                'subCpmks.indicators.classroomWeights' => fn ($q) => $q->where('classroom_id', $classroom->id),
            ])->orderBy('id')->get()
            : collect();

        $scoreMap = $this->buildScoreMap($cpmks);

        $agg = GradeService::aggregateStudent(
            GradeService::fromCpmkCollection($cpmks, $scoreMap, $student->id, $classroom->id)
        );

        $cpmkResults = $this->mergeCpmkPresentation($cpmks, $agg['cpmks'], $classroom->id);
        $finalScore = $agg['final_score'];
        $finalGrade = $agg['final_grade'];
        $finalMutu = $agg['final_mutu'];
        $anyFailed = $agg['any_failed'];
        $complete = $agg['complete'];

        return view('kaprodi.laporan.mahasiswa-kelas', compact(
            'student', 'classroom', 'course', 'cpmkResults', 'finalScore', 'finalGrade', 'finalMutu', 'anyFailed', 'complete'
        ));
    }

    /* ── Arsip: Index ──────────────────────────────────────────────────── */
    public function arsipIndex(Request $request)
    {
        $data = $this->classroomQuery($request, true);

        return view('kaprodi.arsip.index', $data);
    }

    /* ── CPMK Approval: Index ──────────────────────────────────────────── */
    public function cpmkApprovalIndex(Request $request)
    {
        $auth = Auth::user();
        $prodiId = $auth->activeProdiId();

        $query = ClassroomCpmk::with(['classroom.course', 'cpl', 'creator', 'indicators'])
            ->orderByRaw("FIELD(status, 'pending', 'draft', 'approved', 'rejected')")
            ->orderByDesc('updated_at');

        // Scope KETAT: hanya CPMK dari kelas dalam prodi kaprodi (tanpa fallback NULL)
        if ($auth->role === 'kaprodi' && $prodiId) {
            $query->whereHas('classroom', function ($cq) use ($prodiId) {
                $cq->whereHas('course', fn ($q) => $q->where('program_studi_id', $prodiId)
                );
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $cpmks = $query->get();
        $classrooms = Classroom::with('course')->orderByDesc('academic_year')->get();
        $pendingCount = ClassroomCpmk::where('status', 'pending')->count();

        return view('kaprodi.cpmk-approvals.index', compact('cpmks', 'classrooms', 'pendingCount'));
    }

    /* ── CPMK Approval: Show ───────────────────────────────────────────── */
    public function cpmkApprovalShow(ClassroomCpmk $classroomCpmk)
    {
        $classroomCpmk->load(['classroom.course', 'cpl', 'creator', 'indicators.assessments']);
        $templates = $classroomCpmk->classroom->course?->cpmks()->with('cpl')->get() ?? collect();

        return view('kaprodi.cpmk-approvals.show', compact('classroomCpmk', 'templates'));
    }

    /* ── CPMK Approve ──────────────────────────────────────────────────── */
    public function cpmkApprove(Request $request, ClassroomCpmk $classroomCpmk)
    {
        abort_unless($classroomCpmk->status === 'pending', 422, 'CPMK tidak dalam status menunggu persetujuan.');

        $classroomCpmk->update([
            'status' => 'approved',
            'approved_at' => now(),
            'rejection_note' => null,
        ]);

        $classroomCpmk->creator?->notify(new CpmkStatusChanged($classroomCpmk, 'approved'));

        return redirect()
            ->route('kaprodi.cpmk-approvals.index')
            ->with('success', "CPMK {$classroomCpmk->code} telah disetujui.");
    }

    /* ── CPMK Reject ───────────────────────────────────────────────────── */
    public function cpmkReject(Request $request, ClassroomCpmk $classroomCpmk)
    {
        $request->validate([
            'rejection_note' => 'required|string|min:5|max:1000',
        ]);

        abort_unless($classroomCpmk->status === 'pending', 422, 'CPMK tidak dalam status menunggu persetujuan.');

        $classroomCpmk->update([
            'status' => 'rejected',
            'rejection_note' => $request->rejection_note,
            'approved_at' => null,
        ]);

        $classroomCpmk->creator?->notify(new CpmkStatusChanged($classroomCpmk, 'rejected'));

        return redirect()
            ->route('kaprodi.cpmk-approvals.index')
            ->with('success', "CPMK {$classroomCpmk->code} telah ditolak dengan catatan revisi.");
    }
}
