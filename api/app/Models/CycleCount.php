<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CycleCount extends Model
{
    protected $fillable = ['warehouse_id', 'number', 'type', 'blind', 'blocks_picking', 'status', 'responsible_id', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return ['blind' => 'boolean', 'blocks_picking' => 'boolean', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function lines(): HasMany { return $this->hasMany(CycleCountLine::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }

    public function progress(): array
    {
        $total = $this->lines->count();
        $counted = $this->lines->whereNotIn('status', ['PENDIENTE'])->count();
        $diff = $this->lines->whereIn('status', ['DIFERENCIA', 'RECONTAR'])->count();
        $ok = $this->lines->whereIn('status', ['CONTADA', 'APROBADA'])->count();

        return [
            'total' => $total, 'counted' => $counted, 'diff' => $diff,
            'ira' => $counted ? round($ok / $counted * 100, 1) : null,
        ];
    }

    public const TYPE_LABELS = [
        'GENERAL' => 'General', 'UBICACION' => 'Por ubicación', 'PRODUCTO' => 'Por producto',
        'CICLICO_A' => 'Cíclico clase A', 'CICLICO_B' => 'Cíclico clase B', 'CICLICO_C' => 'Cíclico clase C', 'EXCEPCION' => 'Por excepción',
    ];
}
