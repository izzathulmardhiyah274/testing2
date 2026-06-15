<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Cpl;
use App\Models\Cpmk;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MahasiswaController extends Controller
{
    use \App\Http\Controllers\Concerns\BuildsGradeReport;

    public function dashboard()
    {
        $user = Auth::user();

        // Hanya kelas aktif (belum diarsipkan)
        $classrooms = $user->classrooms()
            ->with('course')
            ->where('is_archived', false)
            ->orderByDesc('academic_year')
            ->orderBy('name')
            ->get();

        return view('mahasiswa.dashboard', compact('classrooms'));
    }

    public function riwayatKelas()
    {
        $user = Auth::user();

        // Hanya kelas yang sudah diarsipkan
        $classrooms = $user->classrooms()
            ->with('course')
            ->where('is_archived', true)
            ->orderByDesc('academic_year')
            ->orderBy('name')
            ->get();

        return view('mahasiswa.riwayat-kelas', compact('classrooms'));
    }

    public function show(Classroom $classroom)
    {
        $user = Auth::user();

        abort_unless($classroom->students()->where('user_id', $user->id)->exists(), 403);

        $course = $classroom->course;

        // CPMK template dari mata kuliah. Komponen difilter ke kelas ini agar tidak
        // tercampur komponen kelas lain yang mengajar mata kuliah yang sama.
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
            GradeService::fromCpmkCollection($cpmks, $scoreMap, $user->id, $classroom->id)
        );

        $cpmkResults = $this->mergeCpmkPresentation($cpmks, $agg['cpmks'], $classroom->id);
        $finalScore = $agg['final_score'];
        $finalGrade = $agg['final_grade'];
        $finalMutu = $agg['final_mutu'];
        $anyFailed = $agg['any_failed'];
        $complete = $agg['complete'];

        return view('mahasiswa.classrooms.show', compact(
            'classroom', 'course', 'cpmkResults', 'finalScore', 'finalGrade', 'finalMutu', 'anyFailed', 'complete'
        ));
    }

    public function enroll(Request $request)
    {
        $validated = $request->validate([
            'enrollment_code' => 'required|string|size:8',
        ]);

        $classroom = Classroom::where('enrollment_code', $validated['enrollment_code'])->first();

        if (! $classroom) {
            return back()->with('error', 'Kode enrollment tidak valid.');
        }

        $user = Auth::user();

        if ($user->classrooms()->where('classroom_id', $classroom->id)->exists()) {
            return back()->with('error', 'Anda sudah terdaftar di kelas ini.');
        }

        $user->classrooms()->attach($classroom->id);

        return back()->with('success', 'Berhasil bergabung ke kelas: '.$classroom->name);
    }

    /**
     * Transkrip Nilai — tampil dua mode (OBE & Konvensional) via query string ?mode=obe|konvensional
     */
    public function transkrip(Request $request)
    {
        $user = Auth::user();
        $mode = $request->input('mode', 'konvensional'); // default konvensional

        $classrooms = $user->classrooms()->with([
            'course.cpls',
            'course.cpmks.cpl',
            'course.cpmks.indicators.assessments.scores', 'course.cpmks.subCpmks.indicators.assessments.scores',
        ])->get()->sortBy(fn ($c) => $c->academic_year.$c->period_type);

        // CPL hanya dari prodi mahasiswa ini (cegah kebocoran lintas prodi).
        $prodiId = $user->profilMahasiswa?->program_studi_id;
        $allCpls = Cpl::orderBy('code')
            ->when($prodiId, fn ($q) => $q->where('program_studi_id', $prodiId))
            ->get();

        $transcriptRows = [];
        // Untuk akumulasi CPL: [cpl_id => [scores]]
        $cplAccumulator = [];

        foreach ($classrooms as $classroom) {
            $course = $classroom->course;
            if (! $course) {
                continue;
            }

            // Pakai template CPMK mata kuliah (tabel obe_cpmk) — sumber sama dengan dosen.
            $cpmks = $course->cpmks;

            $scoreMap = $this->buildScoreMap($cpmks);
            $agg = GradeService::aggregateStudent(
                GradeService::fromCpmkCollection($cpmks, $scoreMap, $user->id, $classroom->id)
            );

            $cpmkRows = [];
            foreach ($cpmks as $ci => $cpmk) {
                $total = $agg['cpmks'][$ci]['total'];

                $cpmkRows[] = [
                    'code' => $cpmk->code,
                    'cpl_id' => $cpmk->cpl_id,
                    'weight' => $cpmk->percentage,
                    'score' => $total ?? 0,
                    'lulus' => $agg['cpmks'][$ci]['lulus'] ?? false,
                ];

                // Akumulasi ke CPL (CPMK yang belum dinilai menyumbang 0).
                if ($cpmk->cpl_id) {
                    $cplAccumulator[$cpmk->cpl_id][] = $total ?? 0;
                }
            }

            $transcriptRows[] = [
                'classroom' => $classroom,
                'course' => $course,
                'cpmks' => $cpmkRows,
                'final_score' => $agg['final_score'] ?? 0,
                'final_grade' => $agg['final_grade'] ?? GradeService::toHuruf(0),
                'final_mutu' => $agg['final_mutu'] ?? GradeService::toMutu(0),
                'final_lulus' => $agg['final_score'] !== null ? ($agg['final_score'] >= 55) : false,
                'any_failed' => $agg['any_failed'],
            ];
        }

        // Hitung ketercapaian CPL — pembagian rata 100% ke semua CPMK pendukung.
        // CPL dengan N master-CPMK → tiap CPMK menyumbang 100/N %.
        // Persen ketercapaian = sum(score_taken) × (100/N) / 100 = sum(scores)/N.
        // CPMK yang belum diambil mahasiswa = 0 (tidak menyumbang).
        // Penyebut dibatasi ke CPL prodi mahasiswa agar tidak menghitung CPMK prodi lain.
        $cpmkSupportCount = Cpmk::selectRaw('cpl_id, COUNT(*) as total')
            ->whereIn('cpl_id', $allCpls->pluck('id'))
            ->groupBy('cpl_id')
            ->pluck('total', 'cpl_id')
            ->toArray();

        $cplAchievement = [];
        foreach ($allCpls as $cpl) {
            $scores = $cplAccumulator[$cpl->id] ?? [];
            $supportN = (int) ($cpmkSupportCount[$cpl->id] ?? 0);
            $denominator = $supportN > 0 ? $supportN : count($scores);
            $achievement = ($denominator > 0 && count($scores))
                ? round(array_sum($scores) / $denominator, 1)
                : null;

            $cplAchievement[$cpl->id] = [
                'cpl' => $cpl,
                'scores' => $scores,
                'support_count' => $supportN,
                'taken_count' => count($scores),
                'average' => $achievement,
                'min_target' => (float) ($cpl->min_target ?? 0),
            ];
        }

        // Hitung IPK dari mutu × SKS
        $ipkRows = collect($transcriptRows)->map(fn ($r) => [
            'mutu' => $r['final_mutu'],
            'sks' => $r['course']->sks ?? 0,
        ])->toArray();
        $ipk = GradeService::hitungIpk($ipkRows);

        $totalSks = collect($transcriptRows)->sum(fn ($r) => $r['course']->sks ?? 0);

        return view('mahasiswa.transkrip', compact(
            'transcriptRows', 'user', 'mode', 'cplAchievement', 'allCpls', 'ipk', 'totalSks'
        ));
    }

    /**
     * KHS (Kartu Hasil Studi) — tampil & cetak per semester.
     * URL: /mahasiswa/khs?period_type=genap&academic_year=2025/2026
     */
    public function khs(Request $request)
    {
        $user = Auth::user();

        // ── Ambil semua kelas yang pernah diikuti, urutkan per semester ──
        $allClassrooms = $user->classrooms()
            ->with(['course.cpmks.indicators.assessments.scores', 'course.cpmks.subCpmks.indicators.assessments.scores'])
            ->get()
            ->sortBy(fn ($c) => $c->academic_year.$c->period_type);

        // ── Buat daftar semester unik yang tersedia ──
        $semesterList = $allClassrooms
            ->map(fn ($c) => [
                'period_type' => $c->period_type,
                'academic_year' => $c->academic_year,
                'sort_key' => $c->academic_year.($c->period_type === 'genap' ? '2' : '1'),
            ])
            ->unique(fn ($s) => $s['period_type'].'|'.$s['academic_year'])
            ->sortBy('sort_key')
            ->values()
            ->toArray();

        // ── Tentukan semester yang aktif (dari query string atau semester terbaru) ──
        $activePeriodType = $request->input('period_type');
        $activeAcademicYear = $request->input('academic_year');

        if (! $activePeriodType || ! $activeAcademicYear) {
            // Default: semester terbaru
            $latest = last($semesterList);
            $activePeriodType = $latest['period_type'] ?? null;
            $activeAcademicYear = $latest['academic_year'] ?? null;
        }

        // ── Filter kelas sesuai semester aktif ──
        $semesterClassrooms = $allClassrooms->filter(
            fn ($c) => $c->period_type === $activePeriodType
                   && $c->academic_year === $activeAcademicYear
        );

        // ── Hitung nilai per mata kuliah untuk semester ini ──
        $khsRows = [];
        $totalSks = 0;
        $totalNilaiSks = 0.0;   // ∑ (mutu × SKS) untuk IPS semester ini

        foreach ($semesterClassrooms as $classroom) {
            $course = $classroom->course;
            if (! $course) {
                continue;
            }

            $agg = $this->aggregateClassroom($classroom, $user->id);
            $finalScore = $agg['final_score'] ?? 0;
            $anyFailed = $agg['any_failed'];

            $sks = (int) ($course->sks ?? 0);
            $mutu = $agg['final_mutu'] ?? GradeService::toMutu(0);
            $nilaiSks = round($mutu * $sks, 2);  // Bobot × SKS

            // Hitung KE (berapa kali mata kuliah ini diambil mahasiswa)
            $ke = $user->classrooms()
                ->where('course_id', $course->id)
                ->whereRaw('CONCAT(academic_year, period_type) <= ?', [$activeAcademicYear.$activePeriodType])
                ->count();

            $khsRows[] = [
                'classroom' => $classroom,
                'course' => $course,
                'final_score' => $finalScore,
                'final_grade' => $agg['final_grade'] ?? GradeService::toHuruf(0),
                'final_mutu' => $mutu,
                'final_lulus' => $finalScore >= 55,
                'any_failed' => $anyFailed,
                'ke' => max(1, $ke),
                'nilai_sks' => $nilaiSks,
            ];

            $totalSks += $sks;
            $totalNilaiSks += $nilaiSks;
        }

        // ── IPS semester ini ──
        $ips = $totalSks > 0 ? round($totalNilaiSks / $totalSks, 2) : 0.0;

        // ── IPK kumulatif (semua semester s.d. semester aktif) ──
        $ipkRows = $allClassrooms
            ->filter(fn ($c) => $c->academic_year < $activeAcademicYear
                || ($c->academic_year === $activeAcademicYear
                    && ($activePeriodType === 'genap' || $c->period_type === $activePeriodType))
            )
            ->map(function ($c) use ($user) {
                $course = $c->course;
                if (! $course) {
                    return null;
                }

                $agg = $this->aggregateClassroom($c, $user->id);
                $mutu = $agg['final_mutu'] ?? GradeService::toMutu(0);

                return ['mutu' => $mutu, 'sks' => (int) ($course->sks ?? 0)];
            })
            ->filter()
            ->values()
            ->toArray();

        $ipk = \App\Services\GradeService::hitungIpk($ipkRows);

        // ── Maks. Beban SKS semester berikutnya (aturan UNRI) ──
        $maxSksBerikutnya = 24; // default
        if ($ips >= 3.00) {
            $maxSksBerikutnya = 24;
        } elseif ($ips >= 2.50) {
            $maxSksBerikutnya = 22;
        } elseif ($ips >= 2.00) {
            $maxSksBerikutnya = 20;
        } else {
            $maxSksBerikutnya = 18;
        }

        // ── Data profil mahasiswa ──
        $profile = $user->profilMahasiswa()->with('programStudi.jurusan')->first();
        $namaProdi = $profile?->programStudi?->nama_prodi ?? 'Teknik Informatika';
        $angkatan = $profile?->nim
            ? substr($profile->nim, 0, 4)
            : (strlen($user->identity ?? '') >= 4 ? substr($user->identity, 0, 4) : '-');

        // ── Pembimbing akademik: ambil dari relasi jika ada, fallback kosong ──
        $pembimbingAkademik = null; // Tambahkan relasi jika tersedia

        // ── Wakil dekan: bisa dikonfigurasi via Setting atau hardcode ──
        $wakilDekan = 'Prof. Dr. Ir. Azriyenni, ST., M.Eng';
        $nipWakilDekan = '197304011999032003';

        return view('mahasiswa.khs', compact(
            'user',
            'khsRows',
            'semesterList',
            'activePeriodType',
            'activeAcademicYear',
            'totalSks',
            'totalNilaiSks',
            'ips',
            'ipk',
            'maxSksBerikutnya',
            'namaProdi',
            'angkatan',
            'pembimbingAkademik',
            'wakilDekan',
            'nipWakilDekan'
        ));
    }

    public function downloadKonvensional()
    {
        $user = Auth::user();

        $classrooms = $user->classrooms()->with([
            'course.cpls',
            'course.cpmks.cpl',
            'course.cpmks.indicators.assessments.scores', 'course.cpmks.subCpmks.indicators.assessments.scores',
        ])->get()->sortBy(fn ($c) => $c->academic_year.$c->period_type);

        $transcriptRows = [];

        foreach ($classrooms as $classroom) {
            $course = $classroom->course;
            if (! $course) {
                continue;
            }

            $transcriptRows[] = $this->transcriptRow($classroom, $user->id);
        }

        $ipkRows = collect($transcriptRows)->map(fn ($r) => ['mutu' => $r['final_mutu'], 'sks' => $r['course']->sks ?? 0])->toArray();
        $ipk = GradeService::hitungIpk($ipkRows);
        $totalSks = collect($transcriptRows)->sum(fn ($r) => $r['course']->sks ?? 0);
        $nilaiMutuKumulatif = collect($transcriptRows)
            ->sum(fn ($r) => round(($r['final_mutu'] ?? 0) * ($r['course']->sks ?? 0), 2));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('mahasiswa.transkrip.pdf-konvensional', array_merge([
            'user' => $user,
            'transcriptRows' => $transcriptRows,
            'totalSks' => $totalSks,
            'ipk' => $ipk,
            'nilaiMutuKumulatif' => $nilaiMutuKumulatif,
        ], $this->transkripIdentitas($user, $ipk)))->setPaper('a4', 'portrait');

        return $pdf->download('transkrip-konvensional-'.$user->identity.'.pdf');
    }

    /**
     * Data identitas mahasiswa & footer untuk header transkrip resmi (format UNRI).
     *
     * @return array<string, mixed>
     */
    private function transkripIdentitas(\App\Models\User $user, float $ipk): array
    {
        $prodi = $user->profilMahasiswa()->with('programStudi')->first()?->programStudi;

        $logoPath = public_path('images/logo_transkrip.png');
        $logoData = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return [
            'logoData' => $logoData,
            'namaPerguruanTinggi' => 'UNIVERSITAS RIAU',
            'namaFakultas' => 'FAKULTAS TEKNIK',
            'programPendidikan' => 'Sarjana',
            'namaProdi' => $prodi?->nama_prodi ?? 'Teknik Informatika',
            'predikat' => $this->predikatLulus($ipk),
            'kotaTandaTangan' => 'Pekanbaru',
            'tanggalCetak' => now()->locale('id')->translatedFormat('d F Y'),
            'wakilDekan' => 'Prof. Dr. Ir. Azriyenni, ST., M.Eng',
            'nipWakilDekan' => '197304011999032003',
        ];
    }

    /**
     * Predikat kelulusan berdasarkan IPK (transkrip sementara UNRI).
     */
    private function predikatLulus(float $ipk): string
    {
        return match (true) {
            $ipk >= 3.01 => 'Sangat Memuaskan',
            $ipk >= 2.76 => 'Memuaskan',
            default => '—',
        };
    }

    public function downloadObe()
    {
        $user = Auth::user();

        $classrooms = $user->classrooms()->with([
            'course.cpls',
            'course.cpmks.cpl',
            'course.cpmks.indicators.assessments.scores', 'course.cpmks.subCpmks.indicators.assessments.scores',
        ])->get()->sortBy(fn ($c) => $c->academic_year.$c->period_type);

        // CPL hanya dari prodi mahasiswa ini (cegah kebocoran lintas prodi).
        $prodiId = $user->profilMahasiswa?->program_studi_id;
        $allCpls = Cpl::orderBy('code')
            ->when($prodiId, fn ($q) => $q->where('program_studi_id', $prodiId))
            ->get();
        $transcriptRows = [];
        $cplAccumulator = [];

        foreach ($classrooms as $classroom) {
            $course = $classroom->course;
            if (! $course) {
                continue;
            }

            $row = $this->transcriptRow($classroom, $user->id);
            $transcriptRows[] = $row;

            foreach ($row['cpmks'] as $cpmkRow) {
                if ($cpmkRow['cpl_id']) {
                    $cplAccumulator[$cpmkRow['cpl_id']][] = $cpmkRow['score'];
                }
            }
        }

        $cpmkSupportCount = Cpmk::selectRaw('cpl_id, COUNT(*) as total')
            ->whereIn('cpl_id', $allCpls->pluck('id'))
            ->groupBy('cpl_id')
            ->pluck('total', 'cpl_id')
            ->toArray();

        $cplAchievement = [];
        foreach ($allCpls as $cpl) {
            $scores = $cplAccumulator[$cpl->id] ?? [];
            $supportN = (int) ($cpmkSupportCount[$cpl->id] ?? 0);
            $denominator = $supportN > 0 ? $supportN : count($scores);
            $achievement = ($denominator > 0 && count($scores))
                ? round(array_sum($scores) / $denominator, 1)
                : null;

            $cplAchievement[$cpl->id] = [
                'cpl' => $cpl,
                'scores' => $scores,
                'support_count' => $supportN,
                'taken_count' => count($scores),
                'average' => $achievement,
                'min_target' => (float) ($cpl->min_target ?? 0),
            ];
        }

        $ipkRows = collect($transcriptRows)->map(fn ($r) => ['mutu' => $r['final_mutu'], 'sks' => $r['course']->sks ?? 0])->toArray();
        $ipk = GradeService::hitungIpk($ipkRows);
        $totalSks = collect($transcriptRows)->sum(fn ($r) => $r['course']->sks ?? 0);
        $nilaiMutuKumulatif = collect($transcriptRows)
            ->sum(fn ($r) => round(($r['final_mutu'] ?? 0) * ($r['course']->sks ?? 0), 2));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('mahasiswa.transkrip.pdf-obe', array_merge([
            'user' => $user,
            'transcriptRows' => $transcriptRows,
            'cplAchievement' => $cplAchievement,
            'totalSks' => $totalSks,
            'ipk' => $ipk,
            'nilaiMutuKumulatif' => $nilaiMutuKumulatif,
        ], $this->transkripIdentitas($user, $ipk)))->setPaper('a4', 'portrait');

        return $pdf->download('transkrip-obe-'.$user->identity.'.pdf');
    }
}
