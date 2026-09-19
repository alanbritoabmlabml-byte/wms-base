<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReasonCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'movement_type', 'requires_approval', 'affects_cost'];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'affects_cost' => 'boolean',
        ];
    }
}
