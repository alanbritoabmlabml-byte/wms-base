<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proyeccion del kardex. La verdad esta en stock_movements: cualquier fila de aqui
 * se puede reconstruir con StockLedger::rebuildBalance().
 */
class StockBalance extends Model
{
    use HasFactory;

    /** qty_available es columna generada en BD: por eso no esta aqui. */
    protected $fillable = [
        'warehouse_id', 'location_id', 'item_id', 'lot_id', 'status',
        'qty', 'qty_allocated', 'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'qty_allocated' => 'decimal:4',
            'qty_available' => 'decimal:4',
            'last_movement_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeWithStock(Builder $query): Builder
    {
        return $query->where('qty', '>', 0);
    }
}
