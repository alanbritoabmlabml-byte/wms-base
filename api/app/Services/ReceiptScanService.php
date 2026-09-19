<?php

namespace App\Services;

use App\Data\MovementData;
use App\Exceptions\Domain\InvalidMovementException;
use App\Exceptions\Domain\ReceiptClosedException;
use App\Models\Item;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\ReceiptLineScan;
use App\Models\StockMovement;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El posteo de recepcion: un escaneo del colector se convierte en
 * receipt_line_scans + stock_movements tipo RECEPCION, y `qty_received` se
 * recalcula siempre como SUM(scans.qty) -- nunca se actualiza a mano.
 */
class ReceiptScanService
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly LotResolver $lots,
    ) {}

    /**
     * @return array{body:array<string,mixed>,status:int}
     */
    public function post(Receipt $receipt, array $payload, User $user, ?int $batchId = null): array
    {
        if (! in_array($receipt->status, Receipt::OPEN_STATUSES, true)) {
            throw new ReceiptClosedException($receipt->number, $receipt->status);
        }

        /** @var ReceiptLine $line */
        $line = ReceiptLine::with(['item.baseUom', 'uom'])
            ->where('receipt_id', $receipt->id)
            ->findOrFail($payload['receipt_line_id']);

        // Idempotencia: el mismo client_uuid devuelve el mismo cuerpo, sin duplicar.
        $existing = ReceiptLineScan::where('client_uuid', $payload['client_uuid'])->first();

        if ($existing) {
            return [
                'body' => $this->buildBody(
                    $existing->movement ?? StockMovement::where('client_uuid', $payload['client_uuid'])->firstOrFail(),
                    $line->fresh(),
                    $existing->location_id,
                    $receipt->warehouse_id,
                    (int) $line->item_id,
                ),
                'status' => 200,
            ];
        }

        /** @var Item $item */
        $item = Item::with('baseUom')->findOrFail($payload['item_id']);

        if ((int) $item->id !== (int) $line->item_id) {
            throw new InvalidMovementException(
                'El item escaneado no corresponde a la linea de la nota',
                ['expected_item_id' => $line->item_id, 'scanned_item_id' => $item->id],
            );
        }

        /** @var Location $location */
        $location = Location::findOrFail($payload['location_id']);

        $lot = $this->lots->resolve($item, $payload['lot_code'] ?? $line->lot_code, $payload['expires_at'] ?? null);

        $scannedUomId = isset($payload['scanned_uom'])
            ? Uom::where('code', $payload['scanned_uom'])->value('id')
            : null;

        $occurredAt = isset($payload['occurred_at'])
            ? Carbon::parse($payload['occurred_at'])
            : Carbon::now();

        // Una sola transaccion: si falla el registro del scan, el movimiento de
        // kardex tampoco queda. StockLedger abre una transaccion anidada
        // (savepoint), asi que su propia atomicidad se mantiene.
        $movement = DB::transaction(function () use (
            $receipt, $line, $item, $location, $lot, $payload, $user, $batchId, $scannedUomId, $occurredAt
        ) {
            $movement = $this->ledger->post(new MovementData(
                clientUuid: $payload['client_uuid'],
                type: 'RECEPCION',
                warehouseId: (int) $receipt->warehouse_id,
                itemId: (int) $item->id,
                lotId: (int) $lot->id,
                qty: (float) $payload['qty'],
                uomId: (int) $item->base_uom_id,
                userId: (int) $user->id,
                fromLocationId: null,
                toLocationId: (int) $location->id,
                toStatus: $payload['status'] ?? 'BUENO',
                qtyScanned: isset($payload['qty_scanned']) ? (float) $payload['qty_scanned'] : (float) $payload['qty'],
                scannedBarcode: $payload['scanned_barcode'] ?? null,
                scannedUomId: $scannedUomId ? (int) $scannedUomId : null,
                documentType: 'RECEPCION',
                documentId: (int) $receipt->id,
                documentLineId: (int) $line->id,
                deviceId: $payload['device_serial'] ?? null,
                occurredAt: $occurredAt,
                postedBatchId: $batchId,
            ));

            ReceiptLineScan::create([
                'receipt_line_id' => $line->id,
                'location_id' => $location->id,
                'qty' => $payload['qty'],
                'client_uuid' => $payload['client_uuid'],
                'user_id' => $user->id,
                'movement_id' => $movement->id,
                'occurred_at' => $occurredAt,
            ]);

            $this->refreshLine($line);

            if ($receipt->status === 'ABIERTA') {
                $receipt->update(['status' => 'EN_PROCESO']);
            }

            return $movement;
        });

        return [
            'body' => $this->buildBody($movement, $line->fresh(), (int) $location->id, (int) $receipt->warehouse_id, (int) $item->id),
            'status' => 201,
        ];
    }

    /** `qty_received` es SUM(receipt_line_scans.qty). Siempre. */
    public function refreshLine(ReceiptLine $line): ReceiptLine
    {
        $received = (float) ReceiptLineScan::where('receipt_line_id', $line->id)->sum('qty');
        $expected = (float) $line->qty_expected;

        $status = match (true) {
            $received <= 0 => 'PENDIENTE',
            $received > $expected + 0.00005 => 'EXCEDIDA',
            $received >= $expected - 0.00005 => 'COMPLETA',
            default => 'PARCIAL',
        };

        $line->forceFill(['qty_received' => $received, 'status' => $status])->save();

        return $line;
    }

    private function buildBody(
        StockMovement $movement,
        ReceiptLine $line,
        int $locationId,
        int $warehouseId,
        int $itemId,
    ): array {
        $location = Location::find($locationId);

        $qty = (float) \App\Models\StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('item_id', $itemId)
            ->sum('qty');

        return [
            'movement' => [
                'id' => $movement->id,
                'uuid' => $movement->uuid,
                'type' => $movement->type,
                'qty' => (float) $movement->qty,
            ],
            'line' => [
                'id' => $line->id,
                'qty_received' => (float) $line->qty_received,
                'status' => $line->status,
            ],
            'balance' => [
                'location' => $location?->code,
                'qty' => round($qty, 4),
            ],
        ];
    }
}
