<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah pertemuan (tatap muka) yang dialokasikan untuk satu Sub-CPMK.
 * Mengikuti model RPS: bobot Sub-CPMK lazimnya proporsional dengan jumlah
 * pertemuan (mis. 2 dari 16). Disimpan sebagai metadata; pengisian opsional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obe_sub_cpmk', function (Blueprint $table): void {
            $table->unsignedTinyInteger('meetings')->nullable()->after('percentage');
        });
    }

    public function down(): void
    {
        Schema::table('obe_sub_cpmk', function (Blueprint $table): void {
            $table->dropColumn('meetings');
        });
    }
};
