<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Columnas «puente» de un rack: tramos donde solo existen los niveles superiores (paso por debajo). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cw_racks', function (Blueprint $t) {
            $t->string('puente', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cw_racks', fn (Blueprint $t) => $t->dropColumn('puente'));
    }
};
