<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bobot indikator yang ditetapkan dosen untuk satu kelas tertentu. Menjadi
 * override atas bobot template {@see Indicator::$percentage} yang dibuat Kaprodi.
 */
class ClassroomIndicatorWeight extends Model
{
    protected $table = 'obe_kelas_indikator_bobot';

    protected $fillable = [
        'classroom_id',
        'indicator_id',
        'percentage',
        'is_auto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_auto' => 'boolean',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }
}
