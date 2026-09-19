<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use HasFactory;

    public const OPEN_STATUSES = ['ABIERTA', 'EN_PROCESO'];

    protected $fillable = [
        'warehouse_id', 'number', 'type', 'status', 'external_ref',
        'supplier_name', 'expected_at', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_at' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class)->orderBy('line_no');
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}
