<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassroomSubCpmk extends Model
{
    protected $table = 'obe_kelas_sub_cpmk';

    protected $fillable = [
        'classroom_cpmk_id',
        'code',
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

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(ClassroomCpmk::class, 'classroom_cpmk_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(ClassroomCpmkIndicator::class, 'classroom_sub_cpmk_id');
    }
}
