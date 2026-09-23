<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CarmenController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\ItemController;
use App\Http\Controllers\Web\LabelTemplateController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\OutboundController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\StockController;
use App\Http\Controllers\Web\TransportController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Escritorio web — Carmen WMS
|--------------------------------------------------------------------------
| Todas las pantallas operan sobre el almacén de trabajo de la sesión
| (middleware ResolveWorkingWarehouse). La API del colector vive en routes/api.php.
*/

// ---------------------------------------------------------------------
// Carmen WMS — escritorio + colector (misma interfaz del artifact)
// ---------------------------------------------------------------------
Route::get('/', [CarmenController::class, 'app'])->name('home');
Route::get('/carmen/datos.js', [CarmenController::class, 'data'])->name('carmen.data');
Route::get('/login', fn () => redirect()->route('home'))->name('login');
Route::post('/login', [CarmenController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [CarmenController::class, 'logout'])->name('logout');
    Route::post('/carmen/almacen', [CarmenController::class, 'switchWarehouse'])->name('carmen.warehouse');
    Route::put('/carmen/api/{dataset}/{key}', [CarmenController::class, 'upsert'])->where('dataset', '[A-Z_]+')->name('carmen.upsert');
    Route::post('/carmen/api/{dataset}', [CarmenController::class, 'append'])->where('dataset', '[A-Z_]+')->name('carmen.append');
});

// ---------------------------------------------------------------------
// Gestión (pantallas Blade de administración, versión anterior) — /gestion
// ---------------------------------------------------------------------
Route::middleware('auth')->prefix('gestion')->group(function () {
    Route::post('/almacen', [AuthController::class, 'switchWarehouse'])->name('warehouse.switch');
    Route::get('/buscar', [DashboardController::class, 'search'])->name('search');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Ingresos
    Route::prefix('ingresos')->name('receipts.')->group(function () {
        Route::get('/', [ReceiptController::class, 'index'])->name('index');
        Route::get('/crear', [ReceiptController::class, 'create'])->name('create');
        Route::post('/', [ReceiptController::class, 'store'])->name('store');
        Route::get('/{receipt}', [ReceiptController::class, 'show'])->whereNumber('receipt')->name('show');
        Route::post('/{receipt}/cerrar', [ReceiptController::class, 'close'])->middleware('role:ADMIN,SUPERVISOR')->name('close');
        Route::post('/{receipt}/anular', [ReceiptController::class, 'cancel'])->middleware('role:ADMIN,SUPERVISOR')->name('cancel');
    });

    // Pedidos y despacho
    Route::prefix('salidas')->name('outbound.')->group(function () {
        Route::get('/', [OutboundController::class, 'board'])->name('board');
        Route::get('/pedidos', [OutboundController::class, 'orders'])->name('orders');
        Route::get('/pedidos/{order}', [OutboundController::class, 'show'])->whereNumber('order')->name('show');
        Route::post('/pedidos/{order}/asignar', [OutboundController::class, 'assign'])->name('assign');
        Route::post('/pedidos/{order}/avanzar', [OutboundController::class, 'advance'])->name('advance');
        Route::post('/olas', [OutboundController::class, 'releaseWave'])->middleware('role:ADMIN,SUPERVISOR')->name('wave');
        Route::get('/despachos', [OutboundController::class, 'dispatches'])->name('dispatches');
        Route::post('/despachos', [OutboundController::class, 'storeDispatch'])->middleware('role:ADMIN,SUPERVISOR')->name('dispatches.store');
        Route::post('/despachos/{dispatch}/estado', [OutboundController::class, 'dispatchStatus'])->name('dispatches.status');
    });

    // Stock e inventario
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [StockController::class, 'balances'])->name('balances');
        Route::get('/kardex', [StockController::class, 'kardex'])->name('kardex');
        Route::get('/ajustes', [StockController::class, 'adjustments'])->name('adjustments');
        Route::post('/ajustes', [StockController::class, 'storeAdjustment'])->name('adjustments.store');
        Route::post('/ajustes/{adjustment}/resolver', [StockController::class, 'resolveAdjustment'])->middleware('role:ADMIN,SUPERVISOR')->name('adjustments.resolve');
        Route::get('/conteos', [StockController::class, 'counts'])->name('counts');
        Route::post('/conteos', [StockController::class, 'storeCount'])->middleware('role:ADMIN,SUPERVISOR')->name('counts.store');
        Route::get('/conteos/{count}', [StockController::class, 'showCount'])->whereNumber('count')->name('counts.show');
        Route::post('/conteos/{count}/finalizar', [StockController::class, 'closeCount'])->middleware('role:ADMIN,SUPERVISOR')->name('counts.close');
        Route::get('/abc', [StockController::class, 'abc'])->name('abc');
        Route::get('/minmax', [StockController::class, 'minmax'])->name('minmax');
    });

    // Mapa de almacén
    Route::prefix('mapa')->name('map.')->group(function () {
        Route::get('/', [MapController::class, 'index'])->name('index');
        Route::post('/racks', [MapController::class, 'storeRack'])->middleware('role:ADMIN,SUPERVISOR')->name('racks.store');
        Route::post('/ubicaciones', [MapController::class, 'storeLocation'])->middleware('role:ADMIN,SUPERVISOR')->name('locations.store');
        Route::post('/ubicaciones/{location}/bloqueo', [MapController::class, 'toggleLocation'])->middleware('role:ADMIN,SUPERVISOR')->name('locations.toggle');
    });

    // Maestros
    Route::resource('productos', ItemController::class)->parameters(['productos' => 'item'])->names('items')->only(['index', 'show', 'store', 'update']);
    Route::resource('clientes', CustomerController::class)->parameters(['clientes' => 'customer'])->names('customers')->only(['index', 'store', 'update']);
    Route::prefix('transporte')->name('transport.')->group(function () {
        Route::get('/', [TransportController::class, 'index'])->name('index');
        Route::post('/choferes', [TransportController::class, 'storeDriver'])->name('drivers.store');
        Route::put('/choferes/{driver}', [TransportController::class, 'updateDriver'])->name('drivers.update');
        Route::post('/vehiculos', [TransportController::class, 'storeVehicle'])->name('vehicles.store');
        Route::put('/vehiculos/{vehicle}', [TransportController::class, 'updateVehicle'])->name('vehicles.update');
    });

    // Configuración (solo administradores)
    Route::prefix('configuracion')->name('config.')->middleware('role:ADMIN')->group(function () {
        Route::get('/importar', [ImportController::class, 'index'])->name('import');
        Route::post('/importar/archivo', [ImportController::class, 'upload'])->name('import.upload');
        Route::get('/importar/{batch}', [ImportController::class, 'show'])->whereNumber('batch')->name('import.show');
        Route::post('/importar/{batch}/mapeo', [ImportController::class, 'map'])->name('import.map');
        Route::post('/importar/{batch}/confirmar', [ImportController::class, 'commit'])->name('import.commit');
        Route::post('/importar/{batch}/descartar', [ImportController::class, 'discard'])->name('import.discard');
        Route::get('/importar/plantilla/{dataset}', [ImportController::class, 'template'])->name('import.template');

        Route::get('/etiquetas', [LabelTemplateController::class, 'index'])->name('labels');
        Route::put('/etiquetas/{template}', [LabelTemplateController::class, 'update'])->name('labels.update');
        Route::get('/etiquetas/{template}/muestras', [LabelTemplateController::class, 'samples'])->name('labels.samples');

        Route::get('/usuarios', [UserController::class, 'index'])->name('users');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/usuarios/{user}/credenciales', [UserController::class, 'resetCredentials'])->name('users.reset');

        Route::get('/colectores', [DeviceController::class, 'index'])->name('devices');
        Route::post('/colectores', [DeviceController::class, 'store'])->name('devices.store');
        Route::put('/colectores/{device}', [DeviceController::class, 'update'])->name('devices.update');

        Route::get('/parametros', [SettingController::class, 'index'])->name('settings');
        Route::put('/parametros', [SettingController::class, 'update'])->name('settings.update');
    });
});
