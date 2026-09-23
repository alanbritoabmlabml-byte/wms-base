<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carmen WMS v0.4 — sistema operativo completo:
 * almacén en cada documento (multi-almacén real), ajustes con aprobación,
 * parámetros y plantillas de etiqueta guardados, detalle de conteos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cw_racks', function (Blueprint $t) {
            $t->string('wh', 40)->default('BOL2')->index();
            $t->string('tipo', 60)->nullable();
            $t->integer('cap')->nullable();
        });
        Schema::table('cw_locations', function (Blueprint $t) {
            $t->string('tipo', 60)->nullable();
        });
        Schema::table('cw_ingresos', function (Blueprint $t) {
            $t->string('wh', 40)->default('BOL2')->index();
            $t->text('obs')->nullable();
        });
        Schema::table('cw_pedidos', function (Blueprint $t) {
            $t->string('wh', 40)->default('BOL2')->index();
            $t->text('obs')->nullable();
        });
        Schema::table('cw_despachos', function (Blueprint $t) {
            $t->string('wh', 40)->default('BOL2')->index();
            $t->string('salida', 40)->nullable();
        });
        Schema::table('cw_kardex', function (Blueprint $t) {
            $t->string('wh', 40)->default('BOL2')->index();
        });
        Schema::table('cw_conteos', function (Blueprint $t) {
            $t->json('lines')->nullable();
            $t->string('alcance', 120)->nullable();
            $t->string('modalidad', 120)->nullable();
            $t->boolean('bloquear')->nullable();
        });
        Schema::table('cw_clients', function (Blueprint $t) {
            $t->string('direccion', 255)->nullable();
            $t->string('zona', 120)->nullable();
        });
        Schema::table('cw_users_desk', function (Blueprint $t) {
            $t->string('correo', 160)->nullable();
        });
        Schema::table('cw_users_col', function (Blueprint $t) {
            $t->string('documento', 60)->nullable();
        });

        Schema::create('cw_ajustes', function (Blueprint $t) {
            $t->id();
            $t->integer('position')->default(0)->index();
            $t->string('nro', 40)->unique();
            $t->string('fecha', 40)->nullable();
            $t->string('wh', 40)->default('BOL2')->index();
            $t->string('loc', 60)->nullable();
            $t->string('codigo', 60)->nullable();
            $t->string('lote', 60)->nullable();
            $t->string('tipo', 60)->nullable();
            $t->integer('qty')->nullable();
            $t->string('motivo', 120)->nullable();
            $t->text('obs')->nullable();
            $t->string('username', 60)->nullable();
            $t->string('estado', 40)->nullable();
            $t->string('aprobador', 60)->nullable();
            $t->string('doc', 60)->nullable();
            $t->timestamps();
        });
        Schema::create('cw_settings', function (Blueprint $t) {
            $t->id();
            $t->integer('position')->default(0);
            $t->string('k', 120)->unique();
            $t->json('v')->nullable();
            $t->timestamps();
        });
        Schema::create('cw_labels', function (Blueprint $t) {
            $t->id();
            $t->integer('position')->default(0);
            $t->string('tpl', 60)->unique();
            $t->json('cfg')->nullable();
            $t->integer('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cw_labels');
        Schema::dropIfExists('cw_settings');
        Schema::dropIfExists('cw_ajustes');
        foreach ([
            'cw_racks' => ['wh', 'tipo', 'cap'], 'cw_locations' => ['tipo'], 'cw_ingresos' => ['wh', 'obs'],
            'cw_pedidos' => ['wh', 'obs'], 'cw_despachos' => ['wh', 'salida'], 'cw_kardex' => ['wh'],
            'cw_conteos' => ['lines', 'alcance', 'modalidad', 'bloquear'], 'cw_clients' => ['direccion', 'zona'],
            'cw_users_desk' => ['correo'], 'cw_users_col' => ['documento'],
        ] as $table => $cols) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
