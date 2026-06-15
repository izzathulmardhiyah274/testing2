<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-CPMK versi kelas (instance dosen): cermin dari obe_sub_cpmk.
 * Hierarki kelas: Kelas-CPMK → Kelas-Sub-CPMK → Kelas-Indikator → Kelas-Komponen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obe_kelas_sub_cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_cpmk_id')->constrained('obe_kelas_cpmk')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->text('description');
            $table->decimal('percentage', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_kelas_sub_cpmk');
    }
};
