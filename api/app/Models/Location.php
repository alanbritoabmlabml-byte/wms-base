<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_id', 'zone_id', 'code', 'barcode',
        'aisle', 'rack', 'level', 'position', 'sort_seq',
        'max_weight_kg', 'max_volume_m3', 'is_mixing_allowed', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_seq' => 'integer',
            'max_weight_kg' => 'decimal:3',
            'max_volume_m3' => 'decimal:4',
            'is_mixing_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
