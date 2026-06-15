<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BahanKajian extends Model
{
    protected $table = 'obe_bahan_kajian';

    protected $fillable = ['program_studi_id', 'code', 'name', 'description'];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function cpls(): BelongsToMany
    {
        return $this->belongsToMany(Cpl::class, 'obe_cpl_bahan_kajian', 'bahan_kajian_id', 'cpl_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'obe_mata_kuliah_bahan_kajian', 'bahan_kajian_id', 'course_id');
    }
}
