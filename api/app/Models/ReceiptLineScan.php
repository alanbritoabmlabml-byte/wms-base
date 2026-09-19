<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptLineScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_line_id', 'location_id', 'qty', 'client_uuid',
        'user_id', 'movement_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class, 'receipt_line_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }
}
