<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    protected $table = 'obe_indikator';

    protected $fillable = [
        'cpmk_id',
        'description',
        'percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    public function cpmk()
    {
        return $this->belongsTo(Cpmk::class);
    }

    public function subCpmk()
    {
        return $this->belongsTo(SubCpmk::class);
    }

    /**
     * Semua komponen penilaian untuk indikator ini (semua kelas).
     */
    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Komponen penilaian untuk indikator ini, difilter per kelas.
     * Dipakai di view dosen agar setiap dosen/kelas punya komponen sendiri.
     *
     * Contoh: $indicator->assessmentsForClassroom($classroomId)
     */
    public function assessmentsForClassroom(int $classroomId)
    {
        return $this->hasMany(Assessment::class)
            ->where('classroom_id', $classroomId);
    }

    /**
     * Bobot indikator per-kelas yang ditetapkan dosen (override template).
     */
    public function classroomWeights()
    {
        return $this->hasMany(ClassroomIndicatorWeight::class);
    }

    /**
     * Bobot efektif indikator untuk satu kelas: pakai override dosen bila ada,
     * jika tidak pakai bobot template Kaprodi ({@see $percentage}) sebagai default.
     */
    public function weightForClassroom(?int $classroomId): float
    {
        if ($classroomId === null) {
            return (float) $this->percentage;
        }

        $override = $this->relationLoaded('classroomWeights')
            ? $this->classroomWeights->firstWhere('classroom_id', $classroomId)
            : $this->classroomWeights()->where('classroom_id', $classroomId)->first();

        return (float) ($override->percentage ?? $this->percentage);
    }
}
