<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot CPL × Bahan Kajian (Tabel 6 dokumen kurikulum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obe_cpl_bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpl_id')->constrained('obe_cpl')->cascadeOnDelete();
            $table->foreignId('bahan_kajian_id')->constrained('obe_bahan_kajian')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cpl_id', 'bahan_kajian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_cpl_bahan_kajian');
    }
};
