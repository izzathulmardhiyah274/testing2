<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot Mata Kuliah × Bahan Kajian (Tabel 10 dokumen kurikulum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obe_mata_kuliah_bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('obe_mata_kuliah')->cascadeOnDelete();
            $table->foreignId('bahan_kajian_id')->constrained('obe_bahan_kajian')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'bahan_kajian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_mata_kuliah_bahan_kajian');
    }
};
