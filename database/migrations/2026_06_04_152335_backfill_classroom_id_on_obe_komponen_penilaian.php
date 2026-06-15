<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Komponen penilaian (obe_komponen_penilaian) yang dibuat sebelum kolom
     * classroom_id ditambahkan punya classroom_id NULL, sehingga tidak terbaca
     * pada laporan yang memfilter per kelas. Backfill seaman mungkin:
     *  - bila mata kuliah komponen hanya punya satu kelas → pakai kelas itu;
     *  - bila lebih dari satu, coba simpulkan dari kelas mahasiswa yang sudah
     *    diberi nilai pada komponen tersebut;
     *  - bila tetap ambigu, biarkan NULL (perlu penyelesaian manual).
     */
    public function up(): void
    {
        $orphans = DB::table('obe_komponen_penilaian as a')
            ->join('obe_indikator as i', 'a.indicator_id', '=', 'i.id')
            ->join('obe_cpmk as c', 'i.cpmk_id', '=', 'c.id')
            ->whereNull('a.classroom_id')
            ->select('a.id as assessment_id', 'c.course_id')
            ->get();

        foreach ($orphans as $orphan) {
            $classroomIds = DB::table('obe_kelas')
                ->where('course_id', $orphan->course_id)
                ->pluck('id');

            $target = null;

            if ($classroomIds->count() === 1) {
                $target = $classroomIds->first();
            } elseif ($classroomIds->count() > 1) {
                $studentIds = DB::table('obe_nilai_komponen')
                    ->where('assessment_id', $orphan->assessment_id)
                    ->pluck('student_id');

                if ($studentIds->isNotEmpty()) {
                    $candidates = DB::table('obe_kelas_pengguna')
                        ->whereIn('user_id', $studentIds)
                        ->whereIn('classroom_id', $classroomIds)
                        ->distinct()
                        ->pluck('classroom_id');

                    if ($candidates->count() === 1) {
                        $target = $candidates->first();
                    }
                }
            }

            if ($target !== null) {
                DB::table('obe_komponen_penilaian')
                    ->where('id', $orphan->assessment_id)
                    ->update(['classroom_id' => $target]);
            }
        }
    }

    /**
     * Tidak dapat dibalik dengan andal: informasi classroom_id yang semula NULL
     * tidak tersimpan. Sengaja dibiarkan no-op.
     */
    public function down(): void
    {
        //
    }
};
