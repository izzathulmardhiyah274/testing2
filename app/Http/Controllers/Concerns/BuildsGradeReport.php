<?php

namespace App\Http\Controllers\Concerns;

use App\Services\GradeService;

/**
 * Helper bersama untuk membangun rekap nilai dari koleksi CPMK Eloquent.
 * Menjamin Dosen, Kaprodi, dan Mahasiswa memakai sumber & bentuk data yang sama.
 */
trait BuildsGradeReport
{
    /**
     * Agregasi nilai satu mahasiswa untuk satu kelas. Komponen otomatis
     * difilter ke kelas tersebut. Kelas harus memuat relasi
     * `course.cpmks.subCpmks.indicators.assessments.scores` (atau `course.cpmks.indicators.*` untuk CPMK lama tanpa Sub-CPMK).
     *
     * @return array{cpmks: array<int, array<string, mixed>>, final_score: float|null, final_grade: string|null, final_mutu: float|null, any_failed: bool, complete: bool}
     */
    protected function aggregateClassroom(\App\Models\Classroom $classroom, int $studentId): array
    {
        $cpmks = $classroom->course?->cpmks ?? collect();
        $scoreMap = $this->buildScoreMap($cpmks);

        return GradeService::aggregateStudent(
            GradeService::fromCpmkCollection($cpmks, $scoreMap, $studentId, $classroom->id)
        );
    }

    /**
     * Bangun satu baris transkrip/KHS untuk satu kelas: ringkasan per-CPMK
     * beserta nilai akhir. Dipakai transkrip & unduhan PDF.
     *
     * @return array<string, mixed>
     */
    protected function transcriptRow(\App\Models\Classroom $classroom, int $studentId): array
    {
        $course = $classroom->course;
        $cpmks = $course?->cpmks ?? collect();
        $scoreMap = $this->buildScoreMap($cpmks);
        $agg = GradeService::aggregateStudent(
            GradeService::fromCpmkCollection($cpmks, $scoreMap, $studentId, $classroom->id)
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
        }

        return [
            'classroom' => $classroom,
            'course' => $course,
            'cpmks' => $cpmkRows,
            'final_score' => $agg['final_score'] ?? 0,
            'final_grade' => $agg['final_grade'] ?? GradeService::toHuruf(0),
            'final_mutu' => $agg['final_mutu'] ?? GradeService::toMutu(0),
            'final_lulus' => ($agg['final_score'] ?? 0) >= 55,
            'any_failed' => $agg['any_failed'],
        ];
    }

    /**
     * Bangun baris rekap nilai per mahasiswa untuk tabel laporan kelas.
     * $cpmks diasumsikan sudah difilter komponennya ke kelas terkait
     * (lewat eager-load `where('classroom_id', ...)`).
     *
     * @param  iterable  $students
     * @param  iterable<\App\Models\Cpmk>  $cpmks
     * @param  array<int, array<int, float>>  $scoreMap
     * @return array<int, array<string, mixed>>
     */
    protected function buildRows($students, $cpmks, array $scoreMap, ?int $classroomId = null): array
    {
        $rows = [];

        foreach ($students as $student) {
            $agg = GradeService::aggregateStudent(
                GradeService::fromCpmkCollection($cpmks, $scoreMap, $student->id, $classroomId)
            );

            $rows[] = [
                'student' => $student,
                'cpmks' => $this->mergeCpmkPresentation($cpmks, $agg['cpmks'], $classroomId),
                'final_score' => $agg['final_score'],
                'final_grade' => $agg['final_grade'],
                'final_mutu' => $agg['final_mutu'],
                'any_failed' => $agg['any_failed'],
                'complete' => $agg['complete'],
            ];
        }

        return $rows;
    }

    /**
     * Index nilai komponen: [assessment_id][student_id] => nilai.
     * CPMK harus sudah memuat relasi `indicators.assessments.scores`.
     *
     * @param  iterable<\App\Models\Cpmk>  $cpmks
     * @return array<int, array<int, float>>
     */
    protected function buildScoreMap(iterable $cpmks): array
    {
        $scoreMap = [];

        foreach ($cpmks as $cpmk) {
            $indicators = $cpmk->subCpmks->isEmpty()
                ? $cpmk->indicators
                : $cpmk->subCpmks->flatMap(fn ($sub) => $sub->indicators);

            foreach ($indicators as $indicator) {
                foreach ($indicator->assessments as $assessment) {
                    foreach ($assessment->scores as $score) {
                        $scoreMap[$assessment->id][$score->student_id] = (float) $score->score;
                    }
                }
            }
        }

        return $scoreMap;
    }

    /**
     * Gabungkan hasil numerik {@see \App\Services\GradeService::aggregateStudent()}
     * dengan field presentasi (kode, deskripsi, nama, CPL) dari koleksi Eloquent.
     * Urutan dijamin sama karena keduanya berasal dari koleksi $cpmks yang sama.
     *
     * @param  iterable<\App\Models\Cpmk>  $cpmks
     * @param  array<int, array<string, mixed>>  $cpmkAgg
     * @return array<int, array<string, mixed>>
     */
    protected function mergeCpmkPresentation($cpmks, array $cpmkAgg, ?int $classroomId = null): array
    {
        $result = [];

        foreach ($cpmks as $ci => $cpmk) {
            $cAgg = $cpmkAgg[$ci];
            $aggSubs = $cAgg['sub_cpmks'] ?? [];

            // Pasangkan model Sub-CPMK (atau fallback: indikator langsung di bawah
            // CPMK) dengan hasil agregasi, urut sama seperti fromCpmkCollection().
            if ($cpmk->subCpmks->isEmpty()) {
                $sets = [[null, $cpmk->indicators]];
            } else {
                $sets = $cpmk->subCpmks->map(fn ($sub) => [$sub, $sub->indicators])->all();
            }

            $subResults = [];
            $flatIndicators = [];

            foreach ($sets as $si => [$subModel, $indicatorModels]) {
                $sAgg = $aggSubs[$si] ?? ['indicators' => [], 'total' => null, 'weighted' => null];
                $indicatorResults = [];

                foreach ($indicatorModels as $ii => $indicator) {
                    $iAgg = $sAgg['indicators'][$ii];
                    $componentResults = [];

                    foreach ($indicator->assessments as $ai => $assessment) {
                        $componentResults[] = [
                            'id' => $assessment->id,
                            'name' => $assessment->name,
                            'weight' => $assessment->percentage,
                            'raw' => $iAgg['components'][$ai]['raw'],
                            'weighted' => $iAgg['components'][$ai]['weighted'],
                        ];
                    }

                    $indicatorRow = [
                        'id' => $indicator->id,
                        'description' => $indicator->description,
                        'weight' => $indicator->weightForClassroom($classroomId),
                        'components' => $componentResults,
                        'total' => $iAgg['total'],
                        'weighted' => $iAgg['weighted'],
                    ];

                    $indicatorResults[] = $indicatorRow;
                    $flatIndicators[] = $indicatorRow;
                }

                $subResults[] = [
                    'id' => $subModel?->id,
                    'description' => $subModel?->description,
                    'weight' => $subModel?->percentage ?? 100,
                    'indicators' => $indicatorResults,
                    'total' => $sAgg['total'] ?? null,
                    'weighted' => $sAgg['weighted'] ?? null,
                ];
            }

            $result[] = [
                'id' => $cpmk->id,
                'code' => $cpmk->code,
                'cpl' => $cpmk->cpl,
                'weight' => $cpmk->percentage,
                'sub_cpmks' => $subResults,
                // Kompatibilitas: view lama membaca daftar indikator flat per CPMK.
                'indicators' => $flatIndicators,
                'total' => $cAgg['total'],
                'weighted' => $cAgg['weighted'],
                'lulus' => $cAgg['lulus'],
            ];
        }

        return $result;
    }
}
