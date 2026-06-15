<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot Profil Lulusan × CPL (Tabel 5 dokumen kurikulum).
 * Sebelumnya PL dan CPL ada sebagai entitas terpisah tanpa relasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obe_profil_lulusan_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graduate_profile_id')->constrained('obe_profil_lulusan')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('obe_cpl')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['graduate_profile_id', 'cpl_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_profil_lulusan_cpl');
    }
};
