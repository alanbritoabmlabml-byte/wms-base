<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nucleo de stock: saldo (proyeccion) + kardex (verdad). Doc 02, seccion 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('movement_type', 20)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('affects_cost')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('lot_id')->constrained('lots');
            $table->enum('status', ['BUENO', 'OBSERVADO', 'CUARENTENA', 'DANADO'])->default('BUENO');
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('qty_allocated', 18, 4)->default(0);
            // Columna generada: nunca se escribe a mano.
            $table->decimal('qty_available', 18, 4)->storedAs('qty - qty_allocated');
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            // Esta clave es el corazon del sistema.
            $table->unique(['warehouse_id', 'location_id', 'item_id', 'lot_id', 'status'], 'stock_balances_key_unique');
            $table->index(['warehouse_id', 'item_id']);
            $table->index('location_id');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->enum('type', [
                'RECEPCION', 'PUTAWAY', 'REUBICACION', 'PICKING', 'DESPACHO',
                'AJUSTE_POS', 'AJUSTE_NEG', 'TRASPASO_OUT', 'TRASPASO_IN', 'CAMBIO_ESTADO',
            ]);
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('lot_id')->constrained('lots');
            // null = entra desde fuera / sale del almacen.
            $table->foreignId('from_location_id')->nullable()->constrained('locations');
            $table->foreignId('to_location_id')->nullable()->constrained('locations');
            $table->enum('from_status', ['BUENO', 'OBSERVADO', 'CUARENTENA', 'DANADO'])->nullable();
            $table->enum('to_status', ['BUENO', 'OBSERVADO', 'CUARENTENA', 'DANADO'])->nullable();
            // Siempre positivo: el sentido lo da from/to.
            $table->decimal('qty', 18, 4);
            $table->foreignId('uom_id')->constrained('uoms');
            // Lo que el operador realmente escaneo (auditoria).
            $table->decimal('qty_scanned', 18, 4)->nullable();
            $table->string('scanned_barcode', 64)->nullable();
            $table->foreignId('scanned_uom_id')->nullable()->constrained('uoms');
            $table->enum('document_type', ['RECEPCION', 'PICKING', 'CONTEO', 'AJUSTE', 'TRASPASO'])->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('document_line_id')->nullable();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes');
            $table->foreignId('user_id')->constrained('users');
            $table->string('device_id', 64)->nullable();
            // Clave de idempotencia generada en el colector.
            $table->uuid('client_uuid')->unique();
            $table->timestamp('occurred_at');
            $table->foreignId('posted_batch_id')->nullable()->constrained('sync_batches');
            $table->timestamps();

            $table->index(['warehouse_id', 'item_id', 'occurred_at']);
            $table->index(['document_type', 'document_id']);
            $table->index('from_location_id');
            $table->index('to_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('reason_codes');
    }
};
