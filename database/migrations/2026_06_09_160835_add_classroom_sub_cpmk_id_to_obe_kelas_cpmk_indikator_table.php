<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versi kelas dari penyisipan Sub-CPMK (lihat migrasi obe_indikator).
 * Kolom lama `classroom_cpmk_id` dipertahankan untuk kompatibilitas transisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obe_kelas_cpmk_indikator', function (Blueprint $table): void {
            $table->foreignId('classroom_sub_cpmk_id')->nullable()->after('classroom_cpmk_id')
                ->constrained('obe_kelas_sub_cpmk')->nullOnDelete();
        });

        DB::table('obe_kelas_cpmk_indikator')
            ->whereNotNull('classroom_cpmk_id')
            ->distinct()
            ->pluck('classroom_cpmk_id')
            ->each(function ($classroomCpmkId): void {
                $subId = DB::table('obe_kelas_sub_cpmk')->insertGetId([
                    'classroom_cpmk_id' => $classroomCpmkId,
                    'description' => 'Umum',
                    'percentage' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('obe_kelas_cpmk_indikator')
                    ->where('classroom_cpmk_id', $classroomCpmkId)
                    ->whereNull('classroom_sub_cpmk_id')
                    ->update(['classroom_sub_cpmk_id' => $subId]);
            });
    }

    public function down(): void
    {
        Schema::table('obe_kelas_cpmk_indikator', function (Blueprint $table): void {
            $table->dropForeign(['classroom_sub_cpmk_id']);
            $table->dropColumn('classroom_sub_cpmk_id');
        });
    }
};
