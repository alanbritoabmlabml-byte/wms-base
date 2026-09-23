<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = ['plate', 'type', 'brand', 'model', 'capacity_kg', 'volume_m3', 'driver_id', 'status'];

    protected function casts(): array
    {
        return ['capacity_kg' => 'decimal:2', 'volume_m3' => 'decimal:2'];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
