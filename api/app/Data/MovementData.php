<?php

namespace App\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Todo lo que StockLedger necesita para postear un movimiento.
 *
 * Es un DTO inmutable a proposito: el servicio no debe poder mutar la intencion
 * del colector a mitad de la transaccion.
 */
final class MovementData
{
    public readonly CarbonInterface $occurredAt;

    public function __construct(
        public readonly string $clientUuid,
        public readonly string $type,
        public readonly int $warehouseId,
        public readonly int $itemId,
        public readonly int $lotId,
        public readonly float $qty,
        public readonly int $uomId,
        public readonly int $userId,
        public readonly ?int $fromLocationId = null,
        public readonly ?int $toLocationId = null,
        public readonly ?string $fromStatus = null,
        public readonly ?string $toStatus = null,
        public readonly ?float $qtyScanned = null,
        public readonly ?string $scannedBarcode = null,
        public readonly ?int $scannedUomId = null,
        public readonly ?string $documentType = null,
        public readonly ?int $documentId = null,
        public readonly ?int $documentLineId = null,
        public readonly ?int $reasonCodeId = null,
        public readonly ?string $deviceId = null,
        ?CarbonInterface $occurredAt = null,
        public readonly ?int $postedBatchId = null,
    ) {
        $this->occurredAt = $occurredAt ?? Carbon::now();
    }

    /** Estructura lista para insertar en `stock_movements`. */
    public function toMovementAttributes(): array
    {
        return [
            'warehouse_id' => $this->warehouseId,
            'type' => $this->type,
            'item_id' => $this->itemId,
            'lot_id' => $this->lotId,
            'from_location_id' => $this->fromLocationId,
            'to_location_id' => $this->toLocationId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'qty' => $this->qty,
            'uom_id' => $this->uomId,
            'qty_scanned' => $this->qtyScanned,
            'scanned_barcode' => $this->scannedBarcode,
            'scanned_uom_id' => $this->scannedUomId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'document_line_id' => $this->documentLineId,
            'reason_code_id' => $this->reasonCodeId,
            'user_id' => $this->userId,
            'device_id' => $this->deviceId,
            'client_uuid' => $this->clientUuid,
            'occurred_at' => $this->occurredAt,
            'posted_batch_id' => $this->postedBatchId,
        ];
    }
}
