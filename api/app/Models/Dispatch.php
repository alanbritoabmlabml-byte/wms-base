<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dispatch extends Model
{
    public const STATUS_LABELS = ['CARGANDO' => 'Cargando', 'EN_RUTA' => 'En ruta', 'ENTREGADO' => 'Entregado', 'ANULADO' => 'Anulado'];

    protected $fillable = ['warehouse_id', 'number', 'vehicle_id', 'driver_id', 'destination', 'status', 'packages', 'scheduled_at', 'departed_at', 'delivered_at', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'departed_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function driver(): BelongsTo { return $this->belongsTo(Driver::class); }
    public function orders(): BelongsToMany { return $this->belongsToMany(SalesOrder::class, 'dispatch_orders')->withPivot('packages_verified'); }
}
