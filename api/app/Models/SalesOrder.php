<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    public const STATUSES = ['RECIBIDO', 'PREPARACION', 'VALIDADO', 'EMBALADO', 'DESPACHADO'];

    public const STATUS_LABELS = [
        'RECIBIDO' => 'Recibido', 'PREPARACION' => 'Preparación', 'VALIDADO' => 'Validado',
        'EMBALADO' => 'Embalado', 'DESPACHADO' => 'Despachado', 'ANULADO' => 'Anulado',
    ];

    protected $fillable = [
        'warehouse_id', 'number', 'external_ref', 'customer_id', 'status', 'priority', 'wave_code',
        'assigned_to', 'packages', 'ordered_at', 'dispatched_at',
    ];

    protected function casts(): array
    {
        return ['ordered_at' => 'date', 'dispatched_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lines(): HasMany { return $this->hasMany(SalesOrderLine::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function dispatches(): BelongsToMany { return $this->belongsToMany(Dispatch::class, 'dispatch_orders')->withPivot('packages_verified'); }

    public function scopeForWarehouse(Builder $q, int $warehouseId): Builder { return $q->where('warehouse_id', $warehouseId); }
    public function scopeOpen(Builder $q): Builder { return $q->whereNotIn('status', ['DESPACHADO', 'ANULADO']); }

    public function totals(): array
    {
        return [
            'ordered' => (float) $this->lines->sum('qty_ordered'),
            'picked' => (float) $this->lines->sum('qty_picked'),
        ];
    }
}
