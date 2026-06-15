<?php

use App\Models\Cpmk;
use App\Services\GradeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beberapa instalasi (mis. DB yang di-restore dari dump lama) kehilangan kolom
 * bobot pada obe_cpmk & obe_indikator meskipun migrasi penambahannya tercatat
 * sudah berjalan. Akibatnya $cpmk->percentage / $indicator->percentage = NULL,
 * sehingga seluruh bobot = 0 dan total nilai mahasiswa tidak pernah muncul
 * (GradeService menganggap CPMK belum dinilai). Migrasi ini memulihkan kolom
 * yang hilang lalu mengisi bobot default (dibagi rata) agar nilai yang sudah
 * diinput dosen langsung terhitung.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cpmkPercentageAdded = false;
        $indicatorPercentageAdded = false;

        if (! Schema::hasColumn('obe_cpmk', 'percentage')) {
            Schema::table('obe_cpmk', function (Blueprint $table): void {
                $table->decimal('percentage', 5, 2)->default(0)->after('description');
            });
            $cpmkPercentageAdded = true;
        }

        if (! Schema::hasColumn('obe_cpmk', 'meeting_start')) {
            Schema::table('obe_cpmk', function (Blueprint $table): void {
                $table->unsignedTinyInteger('meeting_start')->nullable();
            });
        }

        if (! Schema::hasColumn('obe_cpmk', 'meeting_end')) {
            Schema::table('obe_cpmk', function (Blueprint $table): void {
                $table->unsignedTinyInteger('meeting_end')->nullable();
            });
        }

        if (! Schema::hasColumn('obe_indikator', 'percentage')) {
            Schema::table('obe_indikator', function (Blueprint $table): void {
                $table->decimal('percentage', 5, 2)->default(0)->after('description');
            });
            $indicatorPercentageAdded = true;
        }

        if ($cpmkPercentageAdded) {
            $this->backfillCpmkWeights();
        }

        if ($indicatorPercentageAdded) {
            $this->backfillIndicatorWeights();
        }
    }

    public function down(): void
    {
        // Tidak diturunkan: kolom ini memang seharusnya ada (lihat migrasi
        // 2026_03_18 & 2026_04_19). Menghapusnya kembali akan merusak penilaian.
    }

    /**
     * Bagi 100% rata ke seluruh CPMK tiap mata kuliah, lalu hitung ulang
     * rentang pertemuan mengikuti CpmkController::recalculateMeetings.
     */
    private function backfillCpmkWeights(): void
    {
        Cpmk::query()
            ->get()
            ->groupBy('course_id')
            ->each(function ($cpmks): void {
                $cpmks = $cpmks->sortBy('id')->values();
                $weights = GradeService::distributeAutoWeights($cpmks->count(), 100);

                $current = 1;
                foreach ($cpmks as $i => $cpmk) {
                    $percentage = $weights[$i];
                    $count = max((int) round(($percentage / 100) * 16), 1);

                    $cpmk->forceFill([
                        'percentage' => $percentage,
                        'meeting_start' => $current,
                        'meeting_end' => $current + $count - 1,
                    ])->save();

                    $current += $count;
                }
            });
    }

    /**
     * Bagi 100% rata ke seluruh indikator tiap CPMK.
     */
    private function backfillIndicatorWeights(): void
    {
        Cpmk::query()
            ->with(['indicators' => fn ($q) => $q->orderBy('id')])
            ->get()
            ->each(function (Cpmk $cpmk): void {
                $indicators = $cpmk->indicators->values();
                $weights = GradeService::distributeAutoWeights($indicators->count(), 100);

                foreach ($indicators as $i => $indicator) {
                    $indicator->forceFill(['percentage' => $weights[$i]])->save();
                }
            });
    }
};
