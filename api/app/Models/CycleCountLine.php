<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCountLine extends Model
{
    protected $fillable = ['cycle_count_id', 'location_id', 'item_id', 'lot_id', 'qty_system', 'qty_counted', 'status', 'counted_by', 'counted_at'];

    protected function casts(): array
    {
        return ['qty_system' => 'decimal:4', 'qty_counted' => 'decimal:4', 'counted_at' => 'datetime'];
    }

    public function count(): BelongsTo { return $this->belongsTo(CycleCount::class, 'cycle_count_id'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function lot(): BelongsTo { return $this->belongsTo(Lot::class); }
    public function counter(): BelongsTo { return $this->belongsTo(User::class, 'counted_by'); }
}
