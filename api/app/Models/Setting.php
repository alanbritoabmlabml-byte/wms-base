<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Parámetros de negocio (clave/valor por grupo, opcionalmente por almacén).
 * Los valores globales (warehouse_id null) son el default; un almacén puede
 * sobreescribirlos.
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'warehouse_id', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public function editor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function get(string $group, string $key, mixed $default = null, ?int $warehouseId = null): mixed
    {
        $all = static::allFor($warehouseId);

        return $all[$group][$key] ?? $default;
    }

    /** Mapa group => key => value, con el almacén sobreescribiendo lo global. */
    public static function allFor(?int $warehouseId = null): array
    {
        return Cache::remember("wms.settings.".($warehouseId ?? 'global'), 300, function () use ($warehouseId) {
            $rows = static::query()
                ->whereNull('warehouse_id')
                ->when($warehouseId, fn ($q) => $q->orWhere('warehouse_id', $warehouseId))
                ->orderByRaw('warehouse_id is null desc')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                $map[$row->group][$row->key] = $row->value['v'] ?? null;
            }

            return $map;
        });
    }

    public static function put(string $group, string $key, mixed $value, ?int $userId = null, ?int $warehouseId = null): void
    {
        static::updateOrCreate(
            ['group' => $group, 'key' => $key, 'warehouse_id' => $warehouseId],
            ['value' => ['v' => $value], 'updated_by' => $userId],
        );

        Cache::forget("wms.settings.".($warehouseId ?? 'global'));
        Cache::forget('wms.settings.global');
    }
}
