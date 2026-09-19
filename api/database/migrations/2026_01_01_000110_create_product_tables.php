<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestros de producto (doc 02, seccion 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name', 200);
            // Unidad en la que se guarda SIEMPRE el saldo.
            $table->foreignId('base_uom_id')->constrained('uoms');
            $table->string('category', 60)->nullable();
            $table->boolean('tracks_lot')->default(false);
            $table->boolean('tracks_expiry')->default(false);
            $table->integer('shelf_life_days')->nullable();
            $table->decimal('min_stock', 18, 4)->nullable();
            $table->decimal('max_stock', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
        });

        Schema::create('item_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->string('barcode', 64)->unique();
            // Que unidad representa ese codigo: escanear la caja suma 12, la unidad suma 1.
            $table->foreignId('uom_id')->constrained('uoms');
            $table->decimal('qty_per_scan', 18, 4)->default(1);
            $table->enum('type', ['INTERNO', 'EAN13', 'GS1_128', 'PROVEEDOR'])->default('INTERNO');
            $table->timestamps();

            $table->index('item_id');
        });

        Schema::create('item_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('min_stock', 18, 4)->nullable();
            $table->decimal('max_stock', 18, 4)->nullable();
            $table->foreignId('default_location_id')->nullable()->constrained('locations');
            $table->enum('abc_class', ['A', 'B', 'C'])->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'abc_class']);
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            // '-' cuando el item no lleva lote: el saldo siempre tiene lot_id no nulo.
            $table->string('code', 40);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('supplier_lot', 40)->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'code']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
        Schema::dropIfExists('item_warehouse');
        Schema::dropIfExists('item_barcodes');
        Schema::dropIfExists('items');
        Schema::dropIfExists('uoms');
    }
};
