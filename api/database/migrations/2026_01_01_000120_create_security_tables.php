<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad y operacion (doc 02, seccion 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->nullable()->after('name');
            $table->string('pin', 255)->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('pin');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });

        Schema::create('user_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('role', ['OPERADOR', 'SUPERVISOR', 'ADMIN'])->default('OPERADOR');
            $table->timestamps();

            // Un operador sin fila aqui no ve el almacen. Punto.
            $table->unique(['user_id', 'warehouse_id']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial', 64)->unique();
            $table->string('model', 40)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->unsignedInteger('movements_count')->default(0);
            $table->enum('status', ['OK', 'PARCIAL', 'ERROR'])->default('OK');
            $table->json('error_payload')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'received_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('model', 80);
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('device_id', 64)->nullable();
            $table->timestamps();

            $table->index(['model', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sync_batches');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('user_warehouse');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'pin', 'is_active', 'last_login_at']);
        });
    }
};
