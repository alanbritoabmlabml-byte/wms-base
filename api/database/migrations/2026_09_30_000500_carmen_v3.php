<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carmen WMS v0.5 — pallets consolidados (SSCC), peso por bulto en pedidos y
 * despachos, firmas autocompletadas en documentos y última señal de cada colector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cw_pallets', function (Blueprint $t) {
            $t->id();
            $t->integer('position')->default(0)->index();
            $t->string('sscc', 24)->unique();
            $t->string('wh', 40)->default('BOL2')->index();
            $t->string('loc', 60)->nullable()->index();
            $t->string('estado', 40)->nullable();
            $t->json('lines')->nullable();
            $t->integer('bultos')->nullable();
            $t->float('peso')->nullable();
            $t->string('usuario', 60)->nullable();
            $t->string('fecha', 40)->nullable();
            $t->string('cerrado', 40)->nullable();
            $t->string('pedido', 40)->nullable();
            $t->text('obs')->nullable();
            $t->timestamps();
        });
        Schema::table('cw_pedidos', function (Blueprint $t) {
            $t->float('peso')->nullable();
            $t->float('peso_real')->nullable();
        });
        Schema::table('cw_despachos', function (Blueprint $t) {
            $t->float('peso')->nullable();
            $t->string('despachante', 60)->nullable();
            $t->string('recibe', 160)->nullable();
            $t->text('obs')->nullable();
        });
        Schema::table('cw_devices', function (Blueprint $t) {
            $t->string('ult', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cw_pallets');
        Schema::table('cw_pedidos', fn (Blueprint $t) => $t->dropColumn(['peso', 'peso_real']));
        Schema::table('cw_despachos', fn (Blueprint $t) => $t->dropColumn(['peso', 'despachante', 'recibe', 'obs']));
        Schema::table('cw_devices', fn (Blueprint $t) => $t->dropColumn('ult'));
    }
};
