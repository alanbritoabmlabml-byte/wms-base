<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'factory_code', 'name', 'base_uom_id', 'category', 'subcategory', 'tracks_lot', 'tracks_expiry',
        'shelf_life_days', 'weight_kg', 'price', 'min_stock', 'max_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tracks_lot' => 'boolean',
            'tracks_expiry' => 'boolean',
            'shelf_life_days' => 'integer',
            'min_stock' => 'decimal:4',
            'max_stock' => 'decimal:4',
            'is_active' => 'boolean',
            'weight_kg' => 'decimal:3',
            'price' => 'decimal:2',
        ];
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function warehouseSettings(): HasMany
    {
        return $this->hasMany(ItemWarehouse::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
