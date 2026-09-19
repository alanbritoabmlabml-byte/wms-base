<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBarcode extends Model
{
    use HasFactory;

    protected $fillable = ['item_id', 'barcode', 'uom_id', 'qty_per_scan', 'type'];

    protected function casts(): array
    {
        return ['qty_per_scan' => 'decimal:4'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
