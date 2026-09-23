<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $fillable = [
        'dataset', 'filename', 'mode', 'status', 'rows_total', 'rows_ok', 'rows_error',
        'headers', 'mapping', 'errors', 'stored_path', 'warehouse_id', 'user_id',
    ];

    protected function casts(): array
    {
        return ['headers' => 'array', 'mapping' => 'array', 'errors' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
