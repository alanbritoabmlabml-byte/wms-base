<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogRequest;
use App\Http\Requests\MovementRequest;
use App\Http\Requests\ReceiptScanRequest;
use App\Http\Requests\SyncBatchRequest;
use App\Http\Resources\CatalogLocationResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ReasonCodeResource;
use App\Http\Resources\UomResource;
use App\Models\Device;
use App\Models\Item;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Models\Receipt;
use App\Models\SyncBatch;
use App\Models\Uom;
use App\Services\MovementService;
use App\Services\ReceiptScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SyncController extends Controller
{
    public function __construct(
        private readonly ReceiptScanService $receiptScans,
        private readonly MovementService $movements,
    ) {}

    /**
     * Pull de maestros. Sin `since` es carga inicial (full); con `since` solo deltas.
     * Es lo que permite que el colector trabaje sin senal dentro de la nave.
     */
    public function catalog(CatalogRequest $request): JsonResponse
    {
        $warehouseId = $request->integer('warehouse_id');
        $since = $request->filled('since') ? Carbon::parse($request->input('since')) : null;
        $serverTime = Carbon::now();

        $items = Item::query()
            ->with(['baseUom', 'barcodes.uom'])
            ->when($since, fn ($q) => $q->where('items.updated_at', '>', $since))
            ->get();

        $locations = Location::query()
            ->with('zone')
            ->where('warehouse_id', $warehouseId)
            ->when($since, fn ($q) => $q->where('locations.updated_at', '>', $since))
            ->orderBy('sort_seq')
            ->get();

        $uoms = Uom::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->get();

        $reasonCodes = ReasonCode::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->get();

        return response()->json([
            'server_time' => $serverTime->toIso8601String(),
            'full' => $since === null,
            'items' => ItemResource::collection($items),
            'locations' => CatalogLocationResource::collection($locations),
            'uoms' => UomResource::collection($uoms),
            'reason_codes' => ReasonCodeResource::collection($reasonCodes),
            'deleted' => [
                'items' => $since
                    ? Item::onlyTrashed()->where('deleted_at', '>', $since)->pluck('id')
                    : [],
                'locations' => $since
                    ? Location::onlyTrashed()->where('warehouse_id', $warehouseId)->where('deleted_at', '>', $since)->pluck('id')
                    : [],
            ],
        ]);
    }

    /**
     * Cola offline. Las operaciones se procesan EN ORDEN y cada una en su propia
     * transaccion: un error no tumba el lote. Las que fallan vuelven marcadas
     * para revision manual -- nunca se descartan en silencio.
     */
    public function batch(SyncBatchRequest $request): JsonResponse
    {
        $user = $request->user();

        $device = $request->filled('device_serial')
            ? Device::firstOrCreate(['serial' => $request->string('device_serial')->toString()])
            : null;

        $device?->forceFill(['last_seen_at' => Carbon::now()])->save();

        $batch = SyncBatch::create([
            'device_id' => $device?->id,
            'user_id' => $user->id,
            'received_at' => Carbon::now(),
            'movements_count' => count($request->input('operations')),
            'status' => 'OK',
        ]);

        $results = [];
        $failures = [];

        foreach ($request->input('operations') as $operation) {
            $payload = $operation['payload'] ?? [];
            $clientUuid = $payload['client_uuid'] ?? null;

            try {
                $result = $this->dispatchOperation((string) $operation['endpoint'], $payload, $user, $batch->id);

                $results[] = [
                    'client_uuid' => $clientUuid,
                    'status' => $result['status'],
                    'body' => $result['body'],
                ];
            } catch (Throwable $e) {
                $response = ApiExceptionRenderer::render($e, $request);
                $status = $response?->getStatusCode() ?? 500;
                $body = $response ? json_decode($response->getContent(), true) : ['error' => ['code' => 'SERVER_ERROR']];

                $results[] = ['client_uuid' => $clientUuid, 'status' => $status, 'body' => $body];
                $failures[] = ['client_uuid' => $clientUuid, 'error' => $body['error'] ?? null];
            }
        }

        $batch->forceFill([
            'status' => $failures === [] ? 'OK' : (count($failures) === count($results) ? 'ERROR' : 'PARCIAL'),
            'error_payload' => $failures ?: null,
        ])->save();

        return response()->json([
            'batch_id' => $batch->id,
            'results' => $results,
        ], 207);
    }

    /**
     * Enruta la operacion de la cola al mismo servicio que usa el endpoint HTTP.
     *
     * @return array{body:array<string,mixed>,status:int}
     */
    private function dispatchOperation(string $endpoint, array $payload, $user, int $batchId): array
    {
        $path = '/'.ltrim(parse_url($endpoint, PHP_URL_PATH) ?: $endpoint, '/');
        $path = preg_replace('#^/api/v1#', '', $path);

        if (preg_match('#^/receipts/(\d+)/scans$#', $path, $m)) {
            $receipt = Receipt::findOrFail((int) $m[1]);
            $this->assertWarehouseAccess($user, (int) $receipt->warehouse_id);

            // La cola no pasa por el Form Request, asi que se valida aqui con
            // las mismas reglas: una operacion mal formada no debe llegar al ledger.
            $clean = Validator::make($payload, (new ReceiptScanRequest)->rules())->validate();

            return $this->receiptScans->post($receipt, $clean, $user, $batchId);
        }

        if ($path === '/movements') {
            $this->assertWarehouseAccess($user, (int) ($payload['warehouse_id'] ?? 0));

            $clean = Validator::make($payload, (new MovementRequest)->rules())->validate();

            return $this->movements->post($clean, $user, $batchId);
        }

        abort(404, "Endpoint no soportado en sync/batch: {$path}");
    }

    private function assertWarehouseAccess($user, int $warehouseId): void
    {
        if (! $user->warehouses()->where('warehouses.id', $warehouseId)->exists()) {
            abort(403, 'El usuario no tiene asignado el almacen '.$warehouseId);
        }
    }
}
