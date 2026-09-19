<?php

namespace App\Services;

use App\Data\MovementData;
use App\Exceptions\Domain\InvalidMovementException;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Movimientos genericos (reubicacion, ajuste, cambio de estado).
 * Traduce el payload del colector a MovementData y delega en StockLedger.
 */
class MovementService
{
    /** Que ubicaciones espera cada tipo: [necesita_origen, necesita_destino]. */
    private const SHAPE = [
        'PUTAWAY' => [true, true],
        'REUBICACION' => [true, true],
        'PICKING' => [true, false],
        'DESPACHO' => [true, false],
        'AJUSTE_POS' => [false, true],
        'AJUSTE_NEG' => [true, false],
        'CAMBIO_ESTADO' => [true, true],
    ];

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly LotResolver $lots,
    ) {}

    /**
     * @return array{body:array<string,mixed>,status:int}
     */
    public function post(array $payload, User $user, ?int $batchId = null): array
    {
        $type = $payload['type'];

        $alreadyPosted = StockMovement::where('client_uuid', $payload['client_uuid'])->exists();

        $this->assertShape($type, $payload);

        /** @var Item $item */
        $item = Item::with('baseUom')->findOrFail($payload['item_id']);

        $lot = $this->lots->resolve($item, $payload['lot_code'] ?? null);

        $status = $payload['status'] ?? 'BUENO';

        $movement = $this->ledger->post(new MovementData(
            clientUuid: $payload['client_uuid'],
            type: $type,
            warehouseId: (int) $payload['warehouse_id'],
            itemId: (int) $item->id,
            lotId: (int) $lot->id,
            qty: (float) $payload['qty'],
            uomId: (int) $item->base_uom_id,
            userId: (int) $user->id,
            fromLocationId: isset($payload['from_location_id']) ? (int) $payload['from_location_id'] : null,
            toLocationId: isset($payload['to_location_id']) ? (int) $payload['to_location_id'] : null,
            fromStatus: $payload['from_status'] ?? $status,
            toStatus: $payload['to_status'] ?? $status,
            scannedBarcode: $payload['scanned_barcode'] ?? null,
            documentType: in_array($type, ['AJUSTE_POS', 'AJUSTE_NEG'], true) ? 'AJUSTE' : null,
            reasonCodeId: isset($payload['reason_code_id']) ? (int) $payload['reason_code_id'] : null,
            deviceId: $payload['device_serial'] ?? null,
            occurredAt: isset($payload['occurred_at']) ? Carbon::parse($payload['occurred_at']) : Carbon::now(),
            postedBatchId: $batchId,
        ));

        return [
            'body' => [
                'movement' => [
                    'id' => $movement->id,
                    'uuid' => $movement->uuid,
                    'type' => $movement->type,
                    'qty' => (float) $movement->qty,
                ],
                'balances' => $this->balancesFor($movement),
            ],
            'status' => $alreadyPosted ? 200 : 201,
        ];
    }

    private function assertShape(string $type, array $payload): void
    {
        [$needsFrom, $needsTo] = self::SHAPE[$type] ?? [false, false];

        if ($needsFrom && empty($payload['from_location_id'])) {
            throw new InvalidMovementException("El movimiento {$type} exige from_location_id", ['field' => 'from_location_id']);
        }

        if ($needsTo && empty($payload['to_location_id'])) {
            throw new InvalidMovementException("El movimiento {$type} exige to_location_id", ['field' => 'to_location_id']);
        }

        if ($type === 'CAMBIO_ESTADO') {
            if (($payload['from_location_id'] ?? null) != ($payload['to_location_id'] ?? null)) {
                throw new InvalidMovementException('CAMBIO_ESTADO no mueve fisicamente: origen y destino deben ser la misma ubicacion');
            }

            if (($payload['from_status'] ?? null) === ($payload['to_status'] ?? null)) {
                throw new InvalidMovementException('CAMBIO_ESTADO exige from_status distinto de to_status');
            }
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function balancesFor(StockMovement $movement): array
    {
        $locationIds = array_values(array_filter([$movement->from_location_id, $movement->to_location_id]));

        return StockBalance::query()
            ->with('location')
            ->where('warehouse_id', $movement->warehouse_id)
            ->where('item_id', $movement->item_id)
            ->where('lot_id', $movement->lot_id)
            ->whereIn('location_id', $locationIds)
            ->get()
            ->map(fn (StockBalance $b) => [
                'location' => $b->location?->code,
                'status' => $b->status,
                'qty' => (float) $b->qty,
                'qty_available' => (float) $b->qty_available,
            ])
            ->values()
            ->all();
    }
}
