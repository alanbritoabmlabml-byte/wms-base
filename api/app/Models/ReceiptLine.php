<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id', 'line_no', 'item_id', 'lot_code',
        'qty_expected', 'qty_received', 'uom_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'qty_expected' => 'decimal:4',
            'qty_received' => 'decimal:4',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ReceiptLineScan::class);
    }
}
