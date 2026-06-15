<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sisipkan Sub-CPMK sebagai induk baru Indikator. Kolom lama `cpmk_id`
 * sengaja DIPERTAHANKAN (denormalisasi) agar kode/relasi lama tetap jalan
 * selama transisi; indikator baru mengisi keduanya (cpmk_id = sub.cpmk_id).
 *
 * Backfill: tiap CPMK yang sudah punya indikator diberi satu Sub-CPMK default
 * (bobot 100%) lalu seluruh indikatornya dipindah ke bawah Sub-CPMK itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obe_indikator', function (Blueprint $table): void {
            $table->foreignId('sub_cpmk_id')->nullable()->after('cpmk_id')
                ->constrained('obe_sub_cpmk')->nullOnDelete();
        });

        DB::table('obe_indikator')
            ->whereNotNull('cpmk_id')
            ->distinct()
            ->pluck('cpmk_id')
            ->each(function ($cpmkId): void {
                $subId = DB::table('obe_sub_cpmk')->insertGetId([
                    'cpmk_id' => $cpmkId,
                    'description' => 'Umum',
                    'percentage' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('obe_indikator')
                    ->where('cpmk_id', $cpmkId)
                    ->whereNull('sub_cpmk_id')
                    ->update(['sub_cpmk_id' => $subId]);
            });
    }

    public function down(): void
    {
        Schema::table('obe_indikator', function (Blueprint $table): void {
            $table->dropForeign(['sub_cpmk_id']);
            $table->dropColumn('sub_cpmk_id');
        });
    }
};
