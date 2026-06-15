<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bobot indikator per-kelas. Indikator (obe_indikator) didefinisikan Kaprodi dan
 * dipakai bersama oleh semua kelas pengampu mata kuliah yang sama, tetapi BOBOT
 * tiap indikator ditentukan oleh dosen yang mengajar di kelas terkait. Tabel ini
 * menyimpan override bobot per (kelas, indikator); bila tidak ada barisnya, sistem
 * memakai bobot template obe_indikator.percentage sebagai default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obe_kelas_indikator_bobot', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classroom_id')->constrained('obe_kelas')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('obe_indikator')->cascadeOnDelete();
            $table->decimal('percentage', 5, 2)->default(0);
            $table->boolean('is_auto')->default(false);
            $table->timestamps();

            $table->unique(['classroom_id', 'indicator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_kelas_indikator_bobot');
    }
};
