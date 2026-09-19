<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lot extends Model
{
    use HasFactory;

    /** Codigo de lote usado cuando el item no lleva trazabilidad de lote. */
    public const NONE = '-';

    protected $fillable = ['item_id', 'code', 'manufactured_at', 'expires_at', 'supplier_lot'];

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
