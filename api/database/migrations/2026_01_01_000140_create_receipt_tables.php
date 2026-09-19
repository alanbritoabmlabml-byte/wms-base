<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos operativos de v0.1: solo recepcion (doc 02, seccion 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('number', 40);
            $table->enum('type', ['PRODUCCION', 'COMPRA', 'DEVOLUCION', 'TRASPASO'])->default('PRODUCCION');
            $table->enum('status', ['ABIERTA', 'EN_PROCESO', 'CERRADA', 'ANULADA'])->default('ABIERTA');
            // Documento de WorkCorp / SIMEC.
            $table->string('external_ref', 60)->nullable();
            $table->string('supplier_name', 160)->nullable();
            $table->date('expected_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['warehouse_id', 'number']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('item_id')->constrained('items');
            $table->string('lot_code', 40)->nullable();
            $table->decimal('qty_expected', 18, 4)->default(0);
            // Nunca se actualiza a mano: es SUM(receipt_line_scans.qty).
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->foreignId('uom_id')->constrained('uoms');
            $table->enum('status', ['PENDIENTE', 'PARCIAL', 'COMPLETA', 'EXCEDIDA'])->default('PENDIENTE');
            $table->timestamps();

            $table->unique(['receipt_id', 'line_no']);
        });

        Schema::create('receipt_line_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_line_id')->constrained('receipt_lines')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations');
            $table->decimal('qty', 18, 4);
            $table->uuid('client_uuid')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('receipt_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_line_scans');
        Schema::dropIfExists('receipt_lines');
        Schema::dropIfExists('receipts');
    }
};
