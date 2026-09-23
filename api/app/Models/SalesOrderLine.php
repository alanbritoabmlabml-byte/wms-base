<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    protected $fillable = ['sales_order_id', 'line_no', 'item_id', 'lot_id', 'from_location_id', 'qty_ordered', 'qty_allocated', 'qty_picked', 'uom_id'];

    protected function casts(): array
    {
        return ['qty_ordered' => 'decimal:4', 'qty_allocated' => 'decimal:4', 'qty_picked' => 'decimal:4'];
    }

    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function lot(): BelongsTo { return $this->belongsTo(Lot::class); }
    public function fromLocation(): BelongsTo { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function uom(): BelongsTo { return $this->belongsTo(Uom::class); }
}
