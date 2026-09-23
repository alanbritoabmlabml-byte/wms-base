<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;

/** Contadores del menú lateral, cacheados 60 s por almacén. */
class NavCounts
{
    public static function for(Warehouse $wh): array
    {
        return Cache::remember("wms.nav.{$wh->id}", 60, fn () => [
            'receipts' => Receipt::forWarehouse($wh->id)->whereIn('status', ['ABIERTA', 'EN_PROCESO'])->count(),
            'orders' => SalesOrder::forWarehouse($wh->id)->open()->count(),
            'devices_offline' => Device::where('warehouse_id', $wh->id)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(10)))->count() ?: null,
        ]);
    }

    public static function forget(int $warehouseId): void
    {
        Cache::forget("wms.nav.$warehouseId");
    }
}
