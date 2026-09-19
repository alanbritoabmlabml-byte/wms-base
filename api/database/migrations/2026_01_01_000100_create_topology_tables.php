<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Topologia fisica: company > branch > warehouse > zone > location (doc 02, seccion 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('tax_id', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('timezone', 60)->default('America/La_Paz');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->enum('type', ['PT', 'MP', 'REPUESTOS', 'TRANSITO', 'GENERAL'])->default('GENERAL');
            $table->boolean('allows_negative_stock')->default(false);
            $table->boolean('requires_putaway')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->enum('type', ['ALMACENAJE', 'PICKING', 'RECEPCION', 'DESPACHO', 'CUARENTENA', 'DEVOLUCION'])
                ->default('ALMACENAJE');
            $table->smallInteger('picking_priority')->default(100);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            // warehouse_id esta denormalizado a proposito: evita un join en cada scan.
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('zone_id')->constrained('zones');
            $table->string('code', 30);
            $table->string('barcode', 64);
            $table->string('aisle', 10)->nullable();
            $table->string('rack', 10)->nullable();
            $table->string('level', 10)->nullable();
            $table->string('position', 10)->nullable();
            $table->integer('sort_seq')->default(0);
            $table->decimal('max_weight_kg', 12, 3)->nullable();
            $table->decimal('max_volume_m3', 12, 4)->nullable();
            $table->boolean('is_mixing_allowed')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'barcode']);
            $table->index(['zone_id', 'sort_seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
