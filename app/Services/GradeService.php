<?php

namespace App\Services;

class GradeService
{
    /**
     * Ambang minimum CPMK dinyatakan lulus (skala 0–100)
     */
    const MIN_CPMK_PASS = 70.0;

    /**
     * Cek apakah satu nilai CPMK lulus
     */
    public static function cpmkLulus(float $score): bool
    {
        return $score >= self::MIN_CPMK_PASS;
    }

    /**
     * Hitung nilai akhir mata kuliah (0–100).
     * Jika ada 1+ CPMK yang sudah dinilai namun tidak lulus → otomatis 0 (nilai E).
     *
     * CPMK yang belum dinilai (score === null) dilewati, BUKAN dianggap 0.
     * Bobot CPMK yang sudah dinilai dinormalisasi ulang sehingga penilaian
     * parsial tetap menghasilkan angka yang masuk akal. Untuk kelas yang sudah
     * lengkap (total bobot 100) hasilnya identik dengan weighted-sum biasa.
     *
     * @param  array<int, array{score: float|null, weight: float}>  $cpmkScores
     */
    public static function nilaiAkhirMataKuliah(array $cpmkScores): float
    {
        $scorable = array_filter($cpmkScores, fn (array $item): bool => $item['score'] !== null);

        if (empty($scorable)) {
            return 0.0;
        }

        foreach ($scorable as $item) {
            if (! self::cpmkLulus((float) $item['score'])) {
                return 0.0;
            }
        }

        $totalWeight = array_sum(array_map(fn (array $item): float => (float) $item['weight'], $scorable));

        if ($totalWeight <= 0) {
            return 0.0;
        }

        $weighted = 0.0;
        foreach ($scorable as $item) {
            $weighted += (float) $item['score'] * (float) $item['weight'];
        }

        return round($weighted / $totalWeight, 2);
    }

    /**
     * Agregasi nilai satu mahasiswa dari struktur CPMK → Indikator → Komponen.
     *
     * Aturan:
     * - Komponen/indikator/CPMK yang belum dinilai dilewati (null), bukan 0.
     * - Total tiap level dinormalisasi ulang atas bobot bagian yang sudah dinilai,
     *   sehingga penilaian parsial tidak menjatuhkan nilai secara keliru.
     * - Jika ada CPMK yang sudah dinilai namun < ambang lulus → nilai akhir E (0).
     *
     * @param  array<int, array{
     *     weight: float,
     *     indicators: array<int, array{
     *         weight: float,
     *         components: array<int, array{weight: float, raw: float|null}>
     *     }>
     * }>  $cpmks
     * @return array{
     *     cpmks: array<int, array{total: float|null, weighted: float|null, lulus: bool|null, indicators: array<int, array{total: float|null, weighted: float|null, components: array<int, array{weight: float, raw: float|null, weighted: float|null}>}>}>,
     *     final_score: float|null,
     *     final_grade: string|null,
     *     final_mutu: float|null,
     *     any_failed: bool,
     *     complete: bool
     * }
     */
    public static function aggregateStudent(array $cpmks): array
    {
        $cpmkOut = [];
        $finalParts = [];
        $anyFailed = false;
        $complete = true;

        foreach ($cpmks as $cpmk) {
            $cpmkWeight = (float) ($cpmk['weight'] ?? 0);

            if (isset($cpmk['sub_cpmks'])) {
                $subCpmksOut = [];
                $cpmkWeightedSum = 0.0;
                $scoredSubWeight = 0.0;
                $cpmkHasScore = false;

                foreach ($cpmk['sub_cpmks'] as $subCpmk) {
                    $subWeight = (float) ($subCpmk['weight'] ?? 0);
                    $subWeightFraction = $subWeight / 100;
                    $agg = self::aggregateIndicatorLevel($subCpmk['indicators'] ?? []);
                    $subTotal = $agg['total'];

                    $subCpmksOut[] = [
                        'weight' => $subWeight,
                        'indicators' => $agg['indicators'],
                        'total' => $subTotal,
                        'weighted' => $subTotal !== null ? round($subTotal * $subWeightFraction, 2) : null,
                    ];

                    if ($subTotal !== null) {
                        $cpmkWeightedSum += $subTotal * $subWeightFraction;
                        $scoredSubWeight += $subWeightFraction;
                        $cpmkHasScore = true;
                    }
                }

                $cpmkTotal = $cpmkHasScore && $scoredSubWeight > 0
                    ? round($cpmkWeightedSum / $scoredSubWeight, 2)
                    : null;

                $cpmkEntry = [
                    'weight' => $cpmkWeight,
                    'sub_cpmks' => $subCpmksOut,
                ];
            } else {
                $agg = self::aggregateIndicatorLevel($cpmk['indicators'] ?? []);
                $cpmkTotal = $agg['total'];

                $cpmkEntry = [
                    'weight' => $cpmkWeight,
                    'indicators' => $agg['indicators'],
                ];
            }

            $cpmkEntry['total'] = $cpmkTotal;
            $cpmkEntry['weighted'] = $cpmkTotal !== null ? round($cpmkTotal * ($cpmkWeight / 100), 2) : null;
            $cpmkEntry['lulus'] = $cpmkTotal !== null ? self::cpmkLulus($cpmkTotal) : null;
            $cpmkOut[] = $cpmkEntry;

            if ($cpmkTotal !== null) {
                $finalParts[] = ['score' => $cpmkTotal, 'weight' => $cpmkWeight];
                if (! self::cpmkLulus($cpmkTotal)) {
                    $anyFailed = true;
                }
            } else {
                $complete = false;
            }
        }

        $finalScore = empty($finalParts) ? null : self::nilaiAkhirMataKuliah($finalParts);
        $konversi = $finalScore !== null ? self::toKonvensional($finalScore) : null;

        return [
            'cpmks' => $cpmkOut,
            'final_score' => $finalScore,
            'final_grade' => $konversi['huruf'] ?? null,
            'final_mutu' => $konversi['mutu'] ?? null,
            'any_failed' => $anyFailed,
            'complete' => $complete && ! empty($finalParts),
        ];
    }

    /**
     * Agregasi satu set indikator → satu nilai "wadah" (CPMK atau Sub-CPMK).
     * Total dinormalisasi ulang atas bobot indikator yang sudah dinilai, sehingga
     * penilaian parsial tidak menjatuhkan nilai. Dipakai oleh jalur 3-tingkat
     * (CPMK→Indikator) maupun 4-tingkat (Sub-CPMK→Indikator).
     *
     * @param  array<int, array{weight: float, components: array<int, array{weight: float, raw: float|null}>}>  $indicators
     * @return array{indicators: array<int, array{weight: float, components: array<int, array{weight: float, raw: float|null, weighted: float|null}>, total: float|null, weighted: float|null}>, total: float|null, has_score: bool}
     */
    private static function aggregateIndicatorLevel(array $indicators): array
    {
        $indicatorsOut = [];
        $weightedSum = 0.0;
        $scoredWeight = 0.0;
        $hasScore = false;

        foreach ($indicators as $indicator) {
            $indicatorWeight = (float) ($indicator['weight'] ?? 0);
            $indicatorWeightFraction = $indicatorWeight / 100;
            $componentsOut = [];
            $indicatorWeightedSum = 0.0;
            $scoredComponentWeight = 0.0;
            $indicatorHasScore = false;

            foreach ($indicator['components'] ?? [] as $component) {
                $componentWeight = (float) ($component['weight'] ?? 0);
                $raw = $component['raw'] ?? null;
                $componentWeightFraction = $componentWeight / 100;
                $weighted = $raw !== null ? round((float) $raw * $componentWeightFraction, 2) : null;

                $componentsOut[] = [
                    'weight' => $componentWeight,
                    'raw' => $raw !== null ? (float) $raw : null,
                    'weighted' => $weighted,
                ];

                if ($raw !== null) {
                    $indicatorWeightedSum += (float) $raw * $componentWeightFraction;
                    $scoredComponentWeight += $componentWeightFraction;
                    $indicatorHasScore = true;
                }
            }

            $indicatorTotal = $indicatorHasScore && $scoredComponentWeight > 0
                ? round($indicatorWeightedSum / $scoredComponentWeight, 2)
                : null;

            $indicatorsOut[] = [
                'weight' => $indicatorWeight,
                'components' => $componentsOut,
                'total' => $indicatorTotal,
                'weighted' => $indicatorTotal !== null ? round($indicatorTotal * $indicatorWeightFraction, 2) : null,
            ];

            if ($indicatorTotal !== null) {
                $weightedSum += $indicatorTotal * $indicatorWeightFraction;
                $scoredWeight += $indicatorWeightFraction;
                $hasScore = true;
            }
        }

        $total = $hasScore && $scoredWeight > 0 ? round($weightedSum / $scoredWeight, 2) : null;

        return ['indicators' => $indicatorsOut, 'total' => $total, 'has_score' => $hasScore];
    }

    /**
     * Bangun input {@see self::aggregateStudent()} dari koleksi Eloquent CPMK.
     * Idealnya CPMK sudah memuat `subCpmks.indicators.assessments`; bila CPMK
     * belum punya Sub-CPMK, indikator langsungnya (`indicators.assessments`)
     * dibungkus sebagai satu Sub-CPMK implisit 100%.
     *
     * Bila $classroomId diberikan, hanya komponen milik kelas tersebut yang
     * diperhitungkan — penting saat satu indikator (template kaprodi) dipakai
     * oleh banyak kelas yang masing-masing punya komponennya sendiri.
     *
     * @param  iterable<\App\Models\Cpmk>  $cpmks
     * @param  array<int, array<int, float>>  $scoreMap  [assessment_id][student_id] => nilai
     * @return array<int, array{weight: float, sub_cpmks: array<int, array{weight: float, indicators: array<int, array{weight: float, components: array<int, array{weight: float, raw: float|null}>}>}>}>
     */
    public static function fromCpmkCollection(iterable $cpmks, array $scoreMap, int $studentId, ?int $classroomId = null): array
    {
        $input = [];

        foreach ($cpmks as $cpmk) {
            $subCpmks = $cpmk->subCpmks;

            if ($subCpmks->isEmpty()) {
                // Fallback: indikator masih langsung di bawah CPMK (data lama yang
                // belum dipetakan ke Sub-CPMK) → bungkus sebagai satu sub 100%.
                $subInput = [[
                    'weight' => 100.0,
                    'indicators' => self::indicatorsToInput($cpmk->indicators, $scoreMap, $studentId, $classroomId),
                ]];
            } else {
                $subInput = [];
                foreach ($subCpmks as $subCpmk) {
                    $subInput[] = [
                        'weight' => (float) $subCpmk->percentage,
                        'indicators' => self::indicatorsToInput($subCpmk->indicators, $scoreMap, $studentId, $classroomId),
                    ];
                }
            }

            $input[] = [
                'weight' => (float) $cpmk->percentage,
                'sub_cpmks' => $subInput,
            ];
        }

        return $input;
    }

    /**
     * Ubah koleksi indikator Eloquent menjadi bentuk input indikator
     * {@see self::aggregateStudent()}. Komponen difilter ke $classroomId bila ada.
     *
     * @param  iterable<\App\Models\Indicator>  $indicators
     * @param  array<int, array<int, float>>  $scoreMap
     * @return array<int, array{weight: float, components: array<int, array{weight: float, raw: float|null}>}>
     */
    private static function indicatorsToInput(iterable $indicators, array $scoreMap, int $studentId, ?int $classroomId): array
    {
        $out = [];

        foreach ($indicators as $indicator) {
            $components = [];

            foreach ($indicator->assessments as $assessment) {
                if ($classroomId !== null && (int) $assessment->classroom_id !== $classroomId) {
                    continue;
                }

                $components[] = [
                    'weight' => (float) $assessment->percentage,
                    'raw' => $scoreMap[$assessment->id][$studentId] ?? null,
                ];
            }

            $out[] = [
                'weight' => $indicator->weightForClassroom($classroomId),
                'components' => $components,
            ];
        }

        return $out;
    }

    /**
     * Bagi bobot otomatis ke $autoCount item secara merata sehingga totalnya
     * tepat $remaining (sisa dari 100 − bobot manual). Pembulatan dikoreksi pada
     * item terakhir agar tidak ada selisih (mis. 33.33 + 33.33 + 33.34 = 100).
     *
     * @return array<int, float>
     */
    public static function distributeAutoWeights(int $autoCount, float $remaining): array
    {
        if ($autoCount <= 0) {
            return [];
        }

        $remaining = max(0.0, $remaining);
        $base = floor(($remaining / $autoCount) * 100) / 100;
        $remainder = round($remaining - ($base * $autoCount), 2);

        $weights = array_fill(0, $autoCount, $base);
        $weights[$autoCount - 1] = round($base + $remainder, 2);

        return $weights;
    }

    /**
     * Konversi nilai 0–100 ke mutu (0.0–4.0) mengikuti SATU UNRI
     */
    public static function toMutu(float $score): float
    {
        if ($score >= 85) {
            return 4.00;
        }
        if ($score >= 80) {
            return 3.75;
        }
        if ($score >= 75) {
            return 3.50;
        }
        if ($score >= 70) {
            return 3.00;
        }
        if ($score >= 65) {
            return 2.75;
        }
        if ($score >= 60) {
            return 2.50;
        }
        if ($score >= 55) {
            return 2.00;
        }
        if ($score >= 45) {
            return 1.00;
        }

        return 0.00;
    }

    /**
     * Konversi nilai 0–100 ke huruf (A–E) mengikuti SATU UNRI
     */
    public static function toHuruf(float $score): string
    {
        if ($score >= 85) {
            return 'A';
        }
        if ($score >= 80) {
            return 'A-';
        }
        if ($score >= 75) {
            return 'B+';
        }
        if ($score >= 70) {
            return 'B';
        }
        if ($score >= 65) {
            return 'B-';
        }
        if ($score >= 60) {
            return 'C+';
        }
        if ($score >= 55) {
            return 'C';
        }
        if ($score >= 45) {
            return 'D';
        }

        return 'E';
    }

    /**
     * Konversi lengkap: nilai → ['huruf'=>, 'mutu'=>, 'lulus'=>]
     */
    public static function toKonvensional(float $score): array
    {
        return [
            'huruf' => self::toHuruf($score),
            'mutu' => self::toMutu($score),
            'lulus' => $score >= 55,  // minimal C untuk dianggap lulus konvensional
        ];
    }

    /**
     * Hitung IPK (rata-rata mutu berbobot SKS)
     *
     * @param  array  $rows  [['mutu'=>float, 'sks'=>int], ...]
     */
    public static function hitungIpk(array $rows): float
    {
        $totalSks = 0;
        $totalMutu = 0.0;

        foreach ($rows as $r) {
            $sks = (int) ($r['sks'] ?? 0);
            $totalSks += $sks;
            $totalMutu += (float) $r['mutu'] * $sks;
        }

        if ($totalSks === 0) {
            return 0.0;
        }

        return round($totalMutu / $totalSks, 2);
    }
}
