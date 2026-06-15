<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Classroom;
use App\Models\ClassroomIndicatorWeight;
use App\Models\Indicator;
use App\Services\GradeService;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $classroom = Classroom::whereHas('course.cpmks.subCpmks.indicators')->first();
    $cpmk = $classroom->course->cpmks()->whereHas('subCpmks.indicators')->first();
    $sub = $cpmk->subCpmks()->whereHas('indicators')->first();

    // Pastikan ada 2 indikator pada sub ini (buat sementara bila perlu; di-rollback).
    $inds = $sub->indicators()->orderBy('id')->get();
    if ($inds->count() < 2) {
        $inds->push(Indicator::create([
            'cpmk_id' => $cpmk->id,
            'sub_cpmk_id' => $sub->id,
            'description' => 'TEST indikator kedua',
            'percentage' => 0,
        ]));
        $inds = $sub->indicators()->orderBy('id')->get();
    }
    $ind1 = $inds[0];
    $ind2 = $inds[1];

    // Isolasi: hapus komponen kelas ini utk indikator sub (akan di-rollback).
    Assessment::where('classroom_id', $classroom->id)
        ->whereIn('indicator_id', $sub->indicators()->pluck('id'))
        ->delete();

    $sid = 999999;
    // Replikasi logika controller: komponen per-sub, lalu indicator weight = Σ komponen.
    $plan = [
        ['ind' => $ind1->id, 'name' => 'Tugas',  'pct' => 40.0, 'raw' => 80.0],
        ['ind' => $ind1->id, 'name' => 'Kuis',   'pct' => 20.0, 'raw' => 60.0],
        ['ind' => $ind2->id, 'name' => 'UTS',    'pct' => 40.0, 'raw' => 90.0],
    ];
    $indSum = [$ind1->id => 0.0, $ind2->id => 0.0];
    foreach ($plan as $p) {
        $a = Assessment::create([
            'indicator_id' => $p['ind'], 'classroom_id' => $classroom->id,
            'name' => $p['name'], 'percentage' => $p['pct'], 'is_auto' => false,
        ]);
        AssessmentScore::create(['assessment_id' => $a->id, 'student_id' => $sid, 'score' => $p['raw']]);
        $indSum[$p['ind']] += $p['pct'];
    }
    foreach ($indSum as $iid => $sum) {
        ClassroomIndicatorWeight::updateOrCreate(
            ['classroom_id' => $classroom->id, 'indicator_id' => $iid],
            ['percentage' => $sum, 'is_auto' => true],
        );
    }

    echo "Indikator weight (Σ komponen):  ind1={$indSum[$ind1->id]} (harap 60)  ind2={$indSum[$ind2->id]} (harap 40)\n";

    // Jalankan engine utk sub ini.
    $cpmkReload = $classroom->course->cpmks()
        ->where('obe_cpmk.id', $cpmk->id)
        ->with([
            'subCpmks' => fn ($q) => $q->where('id', $sub->id),
            'subCpmks.indicators' => fn ($q) => $q->orderBy('id'),
            'subCpmks.indicators.assessments' => fn ($q) => $q->where('classroom_id', $classroom->id)->orderBy('id'),
            'subCpmks.indicators.classroomWeights' => fn ($q) => $q->where('classroom_id', $classroom->id),
        ])->first();

    $scoreMap = [];
    foreach ($cpmkReload->subCpmks->flatMap->indicators as $i) {
        foreach ($i->assessments as $a) {
            $scoreMap[$a->id][$sid] = (float) Assessment::find($a->id)->scores->firstWhere('student_id', $sid)->score;
        }
    }

    $input = GradeService::fromCpmkCollection(collect([$cpmkReload]), $scoreMap, $sid, $classroom->id);
    $agg = GradeService::aggregateStudent($input);

    $subTotal = $agg['cpmks'][0]['sub_cpmks'][0]['total'];
    $flat = (80.0 * 40 + 60.0 * 20 + 90.0 * 40) / 100; // = 80.0

    echo 'Engine sub total = '.$subTotal.'   |   Flat weighted-sum = '.$flat."\n";
    echo (abs($subTotal - $flat) < 0.01 ? "MATCH ✓ nested = flat\n" : "MISMATCH ✗\n");

    // Cek indikator weight terbaca engine.
    $iw1 = $input[0]['sub_cpmks'][0]['indicators'][0]['weight'];
    $iw2 = $input[0]['sub_cpmks'][0]['indicators'][1]['weight'];
    echo "Bobot indikator dibaca engine: ind1={$iw1} (60), ind2={$iw2} (40)\n";
} finally {
    DB::rollBack();
    echo "Transaksi di-rollback — tidak ada data uji yang tersimpan.\n";
}
