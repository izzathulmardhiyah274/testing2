<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-CPMK (template kurikulum): lapisan antara CPMK dan Indikator.
 * Hierarki: Mata Kuliah → CPMK → Sub-CPMK → Indikator → Komponen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obe_sub_cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpmk_id')->constrained('obe_cpmk')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->text('description');
            $table->decimal('percentage', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_sub_cpmk');
    }
};
