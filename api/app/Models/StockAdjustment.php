<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = [
        'warehouse_id', 'location_id', 'item_id', 'lot_id', 'type', 'qty', 'reason_code_id', 'note',
        'status', 'requested_by', 'approved_by', 'movement_id', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'resolved_at' => 'datetime'];
    }

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function lot(): BelongsTo { return $this->belongsTo(Lot::class); }
    public function reasonCode(): BelongsTo { return $this->belongsTo(ReasonCode::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function movement(): BelongsTo { return $this->belongsTo(StockMovement::class, 'movement_id'); }

    public function signedQty(): float
    {
        return in_array($this->type, ['AJUSTE_NEG', 'MERMA'], true) ? -1 * (float) $this->qty : (float) $this->qty;
    }
}
