<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MovementController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ScanController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — WMS Base
|--------------------------------------------------------------------------
| Prefijo /api/v1 declarado en bootstrap/app.php (apiPrefix).
| El contrato completo esta en docs/05-contrato-api.md: aqui no hay ni un
| endpoint de mas.
*/

// Monitoreo: sin token.
Route::get('/health', HealthController::class);

// Autenticacion.
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware(['auth:sanctum', 'throttle:wms'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Todo lo que toca un almacen pasa por el chequeo de user_warehouse.
    Route::middleware('warehouse.access')->group(function () {
        // Sincronizacion de maestros.
        Route::get('/sync/catalog', [SyncController::class, 'catalog']);

        // Resolucion de escaneo.
        Route::post('/scan/resolve', [ScanController::class, 'resolve']);

        // Consultas de stock.
        Route::get('/stock/by-location/{locationId}', [StockController::class, 'byLocation'])
            ->whereNumber('locationId');
        Route::get('/stock/by-item/{itemId}', [StockController::class, 'byItem'])
            ->whereNumber('itemId');
        Route::get('/stock/kardex', [StockController::class, 'kardex']);

        // Recepcion.
        Route::get('/receipts', [ReceiptController::class, 'index']);
        Route::get('/receipts/{receipt}', [ReceiptController::class, 'show'])->whereNumber('receipt');
        Route::post('/receipts/{receipt}/scans', [ReceiptController::class, 'scans'])->whereNumber('receipt');
        Route::post('/receipts/{receipt}/close', [ReceiptController::class, 'close'])->whereNumber('receipt');

        // Movimientos genericos.
        Route::post('/movements', [MovementController::class, 'store']);
    });

    // La cola offline valida el almacen operacion por operacion (cada una puede
    // ser de un almacen distinto), por eso no pasa por el middleware.
    Route::post('/sync/batch', [SyncController::class, 'batch']);
});
