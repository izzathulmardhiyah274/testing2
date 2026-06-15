<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bahan Kajian (BK) — dimensi kurikulum OBE yang sebelumnya belum dimodelkan.
 * Mengacu Tabel 7 dokumen kurikulum (daftar BK01..BKn per program studi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obe_bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->nullable()->constrained('obe_program_studi')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_bahan_kajian');
    }
};
