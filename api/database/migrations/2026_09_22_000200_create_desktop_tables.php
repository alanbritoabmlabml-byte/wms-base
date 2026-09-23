<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.2 — Escritorio web (Carmen WMS).
 *
 * Maestros nuevos (clientes, choferes, vehículos), configuración (parámetros,
 * plantillas de etiqueta, cargas masivas), ajustes con aprobación e inventario
 * cíclico, y la estructura de salidas (pedidos, picking, despachos).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- columnas adicionales en maestros existentes ---
        Schema::table('items', function (Blueprint $table) {
            $table->string('factory_code', 40)->nullable()->after('sku');
            $table->string('subcategory', 60)->nullable()->after('category');
            $table->decimal('weight_kg', 10, 3)->nullable()->after('shelf_life_days');
            $table->decimal('price', 14, 2)->nullable()->after('weight_kg');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('client_type', ['ESCRITORIO', 'COLECTOR', 'AMBOS'])->default('AMBOS')->after('pin');
            $table->string('document_id', 20)->nullable()->after('client_type');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('warehouse_id')->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('battery_pct')->nullable()->after('app_version');
            $table->unsignedInteger('pending_queue')->default(0)->after('battery_pct');
            $table->string('os_version', 40)->nullable()->after('pending_queue');
            $table->boolean('is_active')->default(true)->after('os_version');
        });

        // --- maestros ---
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 160);
            $table->string('tax_id', 30)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('department', 60)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('zone', 60)->nullable();
            $table->string('channel', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['department', 'city']);
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('document_id', 20)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('license_category', 10)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 200)->nullable();
            $table->enum('status', ['ACTIVO', 'EN_RUTA', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 15)->unique();
            $table->string('type', 40);
            $table->string('brand', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->decimal('volume_m3', 8, 2)->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->enum('status', ['DISPONIBLE', 'EN_RUTA', 'MANTENIMIENTO', 'INACTIVO'])->default('DISPONIBLE');
            $table->timestamps();
            $table->softDeletes();
        });

        // --- configuración ---
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40);
            $table->string('key', 80);
            $table->json('value')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['group', 'key', 'warehouse_id']);
        });

        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();          // UBICACION, ITEM, PALLET, DESPACHO
            $table->string('name', 80);
            $table->unsignedSmallInteger('width_mm')->default(100);
            $table->unsignedSmallInteger('height_mm')->default(50);
            $table->enum('qr_format', ['PLAIN', 'GS1', 'JSON', 'URL'])->default('PLAIN');
            $table->unsignedTinyInteger('qr_size')->default(3);    // 1..4
            $table->unsignedSmallInteger('font_scale')->default(100);
            $table->json('fields');                          // {qr:true, code128:false, ...}
            $table->string('printer', 80)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('dataset', 40);                   // items, customers, locations, drivers, vehicles, users_collector, users_desktop
            $table->string('filename', 200);
            $table->enum('mode', ['UPSERT', 'INSERT', 'REPLACE'])->default('UPSERT');
            $table->enum('status', ['CARGADO', 'MAPEADO', 'VALIDADO', 'CONFIRMADO', 'DESCARTADO'])->default('CARGADO');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_ok')->default(0);
            $table->unsignedInteger('rows_error')->default(0);
            $table->json('headers')->nullable();
            $table->json('mapping')->nullable();
            $table->json('errors')->nullable();
            $table->string('stored_path', 255);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });

        // --- ajustes con aprobación ---
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->enum('type', ['AJUSTE_POS', 'AJUSTE_NEG', 'CAMBIO_ESTADO', 'MERMA']);
            $table->decimal('qty', 18, 4);
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes');
            $table->string('note', 255)->nullable();
            $table->enum('status', ['PENDIENTE', 'APROBADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        // --- inventario cíclico ---
        Schema::create('cycle_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->unsignedInteger('number');
            $table->enum('type', ['GENERAL', 'UBICACION', 'PRODUCTO', 'CICLICO_A', 'CICLICO_B', 'CICLICO_C', 'EXCEPCION']);
            $table->boolean('blind')->default(true);
            $table->boolean('blocks_picking')->default(false);
            $table->enum('status', ['HABILITADO', 'EN_CURSO', 'FINALIZADO', 'ANULADO'])->default('HABILITADO');
            $table->foreignId('responsible_id')->nullable()->constrained('users');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'number']);
        });

        Schema::create('cycle_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_count_id')->constrained('cycle_counts')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->decimal('qty_system', 18, 4)->nullable();
            $table->decimal('qty_counted', 18, 4)->nullable();
            $table->enum('status', ['PENDIENTE', 'CONTADA', 'DIFERENCIA', 'APROBADA', 'RECONTAR'])->default('PENDIENTE');
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            $table->index(['cycle_count_id', 'status']);
        });

        // --- salidas ---
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('number', 40);
            $table->string('external_ref', 60)->nullable();       // referencia WorkCorp
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->enum('status', ['RECIBIDO', 'PREPARACION', 'VALIDADO', 'EMBALADO', 'DESPACHADO', 'ANULADO'])->default('RECIBIDO');
            $table->enum('priority', ['NORMAL', 'URGENTE'])->default('NORMAL');
            $table->string('wave_code', 20)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->unsignedInteger('packages')->default(0);
            $table->date('ordered_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'number']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->foreignId('from_location_id')->nullable()->constrained('locations');
            $table->decimal('qty_ordered', 18, 4);
            $table->decimal('qty_allocated', 18, 4)->default(0);
            $table->decimal('qty_picked', 18, 4)->default(0);
            $table->foreignId('uom_id')->constrained('uoms');
            $table->timestamps();
            $table->unique(['sales_order_id', 'line_no']);
        });

        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('number', 40);
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->string('destination', 120)->nullable();
            $table->enum('status', ['CARGANDO', 'EN_RUTA', 'ENTREGADO', 'ANULADO'])->default('CARGANDO');
            $table->unsignedInteger('packages')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['warehouse_id', 'number']);
        });

        Schema::create('dispatch_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained('dispatches')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders');
            $table->unsignedInteger('packages_verified')->default(0);
            $table->timestamps();
            $table->unique(['dispatch_id', 'sales_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_orders');
        Schema::dropIfExists('dispatches');
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('cycle_count_lines');
        Schema::dropIfExists('cycle_counts');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('label_templates');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('customers');

        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['battery_pct', 'pending_queue', 'os_version', 'is_active']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['client_type', 'document_id']);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['factory_code', 'subcategory', 'weight_kg', 'price']);
        });
    }
};
