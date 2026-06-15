<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubCpmk extends Model
{
    protected $table = 'obe_sub_cpmk';

    protected $fillable = [
        'cpmk_id',
        'code',
        'description',
        'percentage',
        'meetings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'meetings' => 'integer',
        ];
    }

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class);
    }
}
