<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Hitung ketercapaian CPL.
 *
 * Rumus (sementara, bisa disesuaikan):
 *   - Per kelas: untuk tiap CPL, ambil seluruh nilai CPMK (per mahasiswa × CPMK)
 *     yang ter-mapping ke CPL tersebut, lalu rata-rata sederhana → persen.
 *   - Per mahasiswa (akumulatif): untuk tiap CPL, ambil semua nilai CPMK
 *     mahasiswa di seluruh kelas yang ter-mapping ke CPL itu, lalu rata-rata.
 *
 * CPMK yang belum dinilai (score null) di-skip, tidak dihitung sebagai 0.
 */
class CplAchievementService
{
    /**
     * Untuk halaman kaprodi/dosen laporan per kelas.
     *
     * @param  array  $rows  hasil buildRows() — tiap row punya 'cpmks' => [['id','code','total'(score), ...]]
     * @param  Collection  $cpmks  collection CPMK kelas (Cpmk atau ClassroomCpmk) dengan relasi cpl.
     * @return array<int, array{cpl: object, average: float|null, sample_count: int}>
     *                                                                                keyed by cpl_id, urut sesuai cpl->code.
     */
    public static function perClassroom($rows, $cpmks): array
    {
        // Index cpmk_id => CPL (object) dan cpmk_id => bobot kontribusi ke CPL.
        $cpmkToCpl = [];
        $cpmkToWeight = [];
        foreach ($cpmks as $cpmk) {
            if ($cpmk->cpl) {
                $cpmkToCpl[$cpmk->id] = $cpmk->cpl;
                $cpmkToWeight[$cpmk->id] = $cpmk->cpl_weight !== null ? (float) $cpmk->cpl_weight : null;
            }
        }

        // Kumpulkan skor berbobot: cpl_id => [ [score, weight|null], ... ]
        $bucket = [];
        foreach ($rows as $row) {
            foreach ($row['cpmks'] as $cR) {
                $cpl = $cpmkToCpl[$cR['id']] ?? null;
                if (! $cpl) {
                    continue;
                }
                $score = $cR['total'] ?? $cR['score'] ?? null;
                if ($score === null) {
                    continue;
                }
                $bucket[$cpl->id]['cpl'] = $cpl;
                $bucket[$cpl->id]['items'][] = ['score' => (float) $score, 'weight' => $cpmkToWeight[$cR['id']] ?? null];
            }
        }

        // Build hasil — rata-rata BERBOBOT: bobot manual (cpl_weight) dipakai apa
        // adanya, CPMK tanpa bobot berbagi sisa (100 − Σmanual) secara merata.
        $result = [];
        foreach ($bucket as $cplId => $b) {
            $items = $b['items'] ?? [];
            $cpl = $b['cpl'];

            $manualTotal = array_sum(array_map(fn ($i) => $i['weight'] ?? 0, $items));
            $autoCount = count(array_filter($items, fn ($i) => $i['weight'] === null));
            $autoEach = $autoCount > 0 ? max(0.0, 100.0 - $manualTotal) / $autoCount : 0.0;

            $weightedSum = 0.0;
            $weightTotal = 0.0;
            foreach ($items as $i) {
                $w = $i['weight'] ?? $autoEach;
                $weightedSum += $i['score'] * $w;
                $weightTotal += $w;
            }

            $result[$cplId] = [
                'cpl' => $cpl,
                'average' => $weightTotal > 0 ? round($weightedSum / $weightTotal, 1) : null,
                'sample_count' => count($items),
                'support_count' => null,
                'taken_count' => count($items),
                'min_target' => (float) ($cpl->min_target ?? 70),
            ];
        }

        // Urutkan berdasarkan kode CPL
        uasort($result, fn ($a, $b) => strcmp($a['cpl']->code, $b['cpl']->code));

        return $result;
    }
}
