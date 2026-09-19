<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemWarehouse extends Model
{
    use HasFactory;

    protected $table = 'item_warehouse';

    protected $fillable = [
        'item_id', 'warehouse_id', 'min_stock', 'max_stock', 'default_location_id', 'abc_class',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:4',
            'max_stock' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}
