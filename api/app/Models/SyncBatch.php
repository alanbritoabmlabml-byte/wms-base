<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id', 'user_id', 'received_at', 'movements_count', 'status', 'error_payload',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'movements_count' => 'integer',
            'error_payload' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
