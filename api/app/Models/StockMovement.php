<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kardex append-only. Nunca se actualiza ni se borra una fila de esta tabla.
 */
class StockMovement extends Model
{
    use HasFactory;

    /** Tipos que representan una entrada neta al almacen. */
    public const INBOUND_TYPES = ['RECEPCION', 'AJUSTE_POS', 'TRASPASO_IN'];

    /** Tipos que representan una salida neta del almacen. */
    public const OUTBOUND_TYPES = ['PICKING', 'DESPACHO', 'AJUSTE_NEG', 'TRASPASO_OUT'];

    /** Tipos que exigen reason_code_id. */
    public const REASON_REQUIRED_TYPES = ['AJUSTE_POS', 'AJUSTE_NEG'];

    protected $fillable = [
        'uuid', 'warehouse_id', 'type', 'item_id', 'lot_id',
        'from_location_id', 'to_location_id', 'from_status', 'to_status',
        'qty', 'uom_id', 'qty_scanned', 'scanned_barcode', 'scanned_uom_id',
        'document_type', 'document_id', 'document_line_id', 'reason_code_id',
        'user_id', 'device_id', 'client_uuid', 'occurred_at', 'posted_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'qty_scanned' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForItem(Builder $query, int $itemId): Builder
    {
        return $query->where('item_id', $itemId);
    }
}
