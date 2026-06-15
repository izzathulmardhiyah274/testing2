<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bobot kontribusi CPMK terhadap CPL yang didukungnya (sumbu "ke atas").
     * Berbeda dari obe_cpmk.percentage yang hanya untuk nilai mata kuliah.
     * NULL = belum diatur → diperlakukan sebagai bagi rata otomatis dalam CPL.
     */
    public function up(): void
    {
        Schema::table('obe_cpmk', function (Blueprint $table) {
            $table->decimal('cpl_weight', 5, 2)->nullable()->after('percentage');
        });
    }

    public function down(): void
    {
        Schema::table('obe_cpmk', function (Blueprint $table) {
            $table->dropColumn('cpl_weight');
        });
    }
};
