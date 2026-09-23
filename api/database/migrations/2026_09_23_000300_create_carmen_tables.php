<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de Carmen WMS: mismo modelo de datos que el artifact (escritorio + colector).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cw_warehouses', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('code', 255)->unique();
            $table->string('name', 255)->nullable();
            $table->string('short', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->json('bodegas')->nullable();
            $table->string('wh_type', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_racks', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('code', 255)->unique();
            $table->string('name', 255)->nullable();
            $table->integer('filas')->nullable();
            $table->integer('cols')->nullable();
            $table->string('zona', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_products', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('codigo', 255)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('um', 255)->nullable();
            $table->string('cat', 255)->nullable();
            $table->string('sub', 255)->nullable();
            $table->string('rot', 255)->nullable();
            $table->integer('min_qty')->nullable();
            $table->integer('max_qty')->nullable();
            $table->integer('vida_util')->nullable();
            $table->double('precio')->nullable();
            $table->string('estado', 255)->nullable();
            $table->string('fabrica', 255)->nullable();
            $table->double('peso')->nullable();
            $table->boolean('serializado')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_locations', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('code', 255)->unique();
            $table->string('rack', 255)->nullable();
            $table->integer('fila')->nullable();
            $table->integer('col')->nullable();
            $table->string('wh', 255)->nullable();
            $table->integer('cap')->nullable();
            $table->integer('sort_seq')->nullable();
            $table->boolean('blocked')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_stock', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('loc', 255)->nullable();
            $table->string('rack', 255)->nullable();
            $table->string('wh', 255)->nullable();
            $table->string('codigo', 255)->nullable();
            $table->string('lote', 255)->nullable();
            $table->integer('qty')->nullable();
            $table->string('ingreso', 255)->nullable();
            $table->string('venc', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->integer('reservado')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_clients', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('codigo', 255)->unique();
            $table->string('nombre', 255)->nullable();
            $table->string('dpto', 255)->nullable();
            $table->string('ciudad', 255)->nullable();
            $table->string('canal', 255)->nullable();
            $table->string('tel', 255)->nullable();
            $table->string('nit', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->integer('pedidos')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_drivers', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('ci', 255)->unique();
            $table->string('nombre', 255)->nullable();
            $table->string('lic', 255)->nullable();
            $table->string('tel', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->integer('viajes')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_trucks', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('placa', 255)->unique();
            $table->string('tipo', 255)->nullable();
            $table->string('marca', 255)->nullable();
            $table->string('cap', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->string('chofer', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_users_desk', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('username', 255)->unique();
            $table->string('nombre', 255)->nullable();
            $table->string('rol', 255)->nullable();
            $table->json('wh')->nullable();
            $table->string('estado', 255)->nullable();
            $table->string('ult', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_users_col', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('username', 255)->unique();
            $table->string('nombre', 255)->nullable();
            $table->string('rol', 255)->nullable();
            $table->string('wh', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->string('device', 255)->nullable();
            $table->boolean('online')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_devices', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('code', 255)->unique();
            $table->string('modelo', 255)->nullable();
            $table->string('so', 255)->nullable();
            $table->integer('bat')->nullable();
            $table->string('app', 255)->nullable();
            $table->string('username', 255)->nullable();
            $table->string('wh', 255)->nullable();
            $table->boolean('online')->nullable();
            $table->integer('cola')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_ingresos', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('nro', 255)->unique();
            $table->string('fecha', 255)->nullable();
            $table->string('tipo', 255)->nullable();
            $table->string('origen', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->json('lines')->nullable();
            $table->string('doc', 255)->nullable();
            $table->string('usuario', 255)->nullable();
            $table->string('muelle', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_pedidos', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('nro', 255)->unique();
            $table->string('cliente', 255)->nullable();
            $table->string('fecha', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->json('lines')->nullable();
            $table->string('picker', 255)->nullable();
            $table->string('prioridad', 255)->nullable();
            $table->string('transporte', 255)->nullable();
            $table->integer('bultos')->nullable();
            $table->string('ola', 255)->nullable();
            $table->string('ref', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_despachos', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('nro', 255)->unique();
            $table->string('fecha', 255)->nullable();
            $table->string('placa', 255)->nullable();
            $table->string('chofer', 255)->nullable();
            $table->json('pedidos')->nullable();
            $table->integer('bultos')->nullable();
            $table->string('destino', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_kardex', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('ts', 255)->nullable();
            $table->string('tipo', 255)->nullable();
            $table->string('dir', 255)->nullable();
            $table->string('codigo', 255)->nullable();
            $table->string('lote', 255)->nullable();
            $table->integer('qty')->nullable();
            $table->string('from_loc', 255)->nullable();
            $table->string('to_loc', 255)->nullable();
            $table->string('username', 255)->nullable();
            $table->string('doc', 255)->nullable();
            $table->string('device', 255)->nullable();
            $table->boolean('offline')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_conteos', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->integer('nro')->unique();
            $table->string('tipo', 255)->nullable();
            $table->string('wh', 255)->nullable();
            $table->string('apertura', 255)->nullable();
            $table->string('estado', 255)->nullable();
            $table->integer('ubic')->nullable();
            $table->integer('contadas')->nullable();
            $table->integer('dif')->nullable();
            $table->string('resp', 255)->nullable();
            $table->double('ira')->nullable();
            $table->timestamps();
        });
        Schema::create('cw_import_history', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('fecha', 255)->nullable();
            $table->string('dataset', 255)->nullable();
            $table->string('archivo', 255)->nullable();
            $table->integer('filas')->nullable();
            $table->integer('ok_rows')->nullable();
            $table->integer('err_rows')->nullable();
            $table->string('username', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('cw_alerts', function (Blueprint $table) {
            $table->id();
            $table->integer('position')->default(0)->index();
            $table->string('kind', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cw_alerts');
        Schema::dropIfExists('cw_import_history');
        Schema::dropIfExists('cw_conteos');
        Schema::dropIfExists('cw_kardex');
        Schema::dropIfExists('cw_despachos');
        Schema::dropIfExists('cw_pedidos');
        Schema::dropIfExists('cw_ingresos');
        Schema::dropIfExists('cw_devices');
        Schema::dropIfExists('cw_users_col');
        Schema::dropIfExists('cw_users_desk');
        Schema::dropIfExists('cw_trucks');
        Schema::dropIfExists('cw_drivers');
        Schema::dropIfExists('cw_clients');
        Schema::dropIfExists('cw_stock');
        Schema::dropIfExists('cw_locations');
        Schema::dropIfExists('cw_products');
        Schema::dropIfExists('cw_racks');
        Schema::dropIfExists('cw_warehouses');
    }
};
