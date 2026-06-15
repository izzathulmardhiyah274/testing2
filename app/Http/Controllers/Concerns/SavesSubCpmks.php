<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cpmk;
use App\Services\GradeService;

/**
 * Logika simpan struktur Sub-CPMK → Indikator untuk satu CPMK. Dipakai bersama
 * oleh pembuatan mata kuliah (CourseController) dan CRUD CPMK (CpmkController).
 *
 * Bobot tiap level: nilai manual dipakai apa adanya; baris yang dikosongkan
 * dibagi rata dari sisa (100 − total manual). Kolom `cpmk_id` pada indikator
 * diisi (denormalisasi) agar relasi lama tetap konsisten.
 */
trait SavesSubCpmks
{
    /**
     * @param  array<int, mixed>  $subCpmks
     */
    protected function saveSubCpmks(Cpmk $cpmk, array $subCpmks): void
    {
        $rows = $this->normalizeSubRows($subCpmks);
        if ($rows === []) {
            return;
        }

        $subWeights = $this->resolveWeightsFromMeetings(array_column($rows, 'meetings'));

        foreach ($rows as $i => $row) {
            $subCpmk = $cpmk->subCpmks()->create([
                'description' => $row['description'],
                'percentage' => $subWeights[$i],
                'meetings' => $row['meetings'],
            ]);

            $indicatorWeights = $this->resolveWeights(array_column($row['indicators'], 'percentage'));
            foreach ($row['indicators'] as $j => $indicator) {
                $subCpmk->indicators()->create([
                    'cpmk_id' => $cpmk->id,
                    'description' => $indicator['description'],
                    'percentage' => $indicatorWeights[$j],
                ]);
            }
        }
    }

    /**
     * Bersihkan input nested Sub-CPMK: buang baris tanpa deskripsi, normalkan
     * persentase ke float|null dan pertemuan ke int|null.
     *
     * @param  array<int, mixed>  $subCpmks
     * @return array<int, array{description: string, percentage: float|null, meetings: int|null, indicators: array<int, array{description: string, percentage: float|null}>}>
     */
    protected function normalizeSubRows(array $subCpmks): array
    {
        $rows = [];

        foreach ($subCpmks as $sub) {
            if (! is_array($sub)) {
                continue;
            }

            $desc = trim((string) ($sub['description'] ?? ''));
            if ($desc === '') {
                continue;
            }

            $indicators = [];
            foreach ($sub['indicators'] ?? [] as $indicator) {
                $iDesc = trim((string) ($indicator['description'] ?? ''));
                if ($iDesc === '') {
                    continue;
                }
                $indicators[] = [
                    'description' => $iDesc,
                    'percentage' => $this->toPercentage($indicator['percentage'] ?? null),
                ];
            }

            $meetingsRaw = $sub['meetings'] ?? null;
            $rows[] = [
                'description' => $desc,
                'percentage' => $this->toPercentage($sub['percentage'] ?? null),
                'meetings' => is_numeric($meetingsRaw) ? (int) $meetingsRaw : null,
                'indicators' => $indicators,
            ];
        }

        return $rows;
    }

    protected function toPercentage(mixed $value): ?float
    {
        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }

    /**
     * Tetapkan bobot konkret: nilai manual dipertahankan, null dibagi rata dari
     * sisa (100 − total manual).
     *
     * @param  array<int, float|null>  $percentages
     * @return array<int, float>
     */
    protected function resolveWeights(array $percentages): array
    {
        $manualTotal = array_sum(array_filter($percentages, fn ($p): bool => $p !== null));
        $autoCount = count(array_filter($percentages, fn ($p): bool => $p === null));
        $autoWeights = GradeService::distributeAutoWeights($autoCount, max(0.0, 100 - $manualTotal));

        $out = [];
        $autoIndex = 0;
        foreach ($percentages as $p) {
            $out[] = $p === null ? ($autoWeights[$autoIndex++] ?? 0.0) : $p;
        }

        return $out;
    }

    /**
     * Tetapkan bobot Sub-CPMK proporsional terhadap jumlah pertemuannya. User
     * hanya menentukan pertemuan; sistem menghitung persentasenya. Baris tanpa
     * pertemuan dianggap 1 pertemuan agar tetap mendapat porsi.
     *
     * @param  array<int, int|null>  $meetings
     * @return array<int, float>
     */
    protected function resolveWeightsFromMeetings(array $meetings): array
    {
        $counts = array_map(
            fn ($m): int => ($m === null || (int) $m < 1) ? 1 : (int) $m,
            $meetings
        );
        $total = array_sum($counts);

        if ($total <= 0) {
            return array_fill(0, count($meetings), 0.0);
        }

        return array_map(fn (int $c): float => round($c / $total * 100, 2), $counts);
    }
}
