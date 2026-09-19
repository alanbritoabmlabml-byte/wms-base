<?php

namespace App\Services;

use App\Models\ItemWarehouse;
use App\Models\Location;
use App\Models\StockBalance;

/**
 * Sugiere donde acomodar lo recibido. El orden importa: ubicacion fija primero,
 * despues donde ya hay el mismo item (consolida), y recien despues una vacia cercana.
 */
class PutawaySuggester
{
    public function suggest(int $warehouseId, int $itemId, int $limit = 3): array
    {
        $suggestions = [];
        $seen = [];

        $fixed = ItemWarehouse::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->whereNotNull('default_location_id')
            ->with('defaultLocation')
            ->first();

        if ($fixed?->defaultLocation?->is_active) {
            $seen[$fixed->default_location_id] = true;
            $suggestions[] = [
                'location_id' => $fixed->defaultLocation->id,
                'code' => $fixed->defaultLocation->code,
                'reason' => 'UBICACION_FIJA',
            ];
        }

        $sameItem = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->where('qty', '>', 0)
            ->with('location')
            ->get()
            ->sortBy(fn ($b) => $b->location?->sort_seq ?? PHP_INT_MAX);

        foreach ($sameItem as $balance) {
            if (count($suggestions) >= $limit) {
                break;
            }

            if (! $balance->location?->is_active || isset($seen[$balance->location_id])) {
                continue;
            }

            $seen[$balance->location_id] = true;
            $suggestions[] = [
                'location_id' => $balance->location->id,
                'code' => $balance->location->code,
                'reason' => 'MISMO_ITEM',
            ];
        }

        if (count($suggestions) < $limit) {
            $occupied = StockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('qty', '>', 0)
                ->pluck('location_id')
                ->unique()
                ->all();

            $empty = Location::query()
                ->where('warehouse_id', $warehouseId)
                ->where('is_active', true)
                ->whereNotIn('id', array_merge($occupied, array_keys($seen)))
                ->orderBy('sort_seq')
                ->limit($limit - count($suggestions))
                ->get();

            foreach ($empty as $location) {
                $suggestions[] = [
                    'location_id' => $location->id,
                    'code' => $location->code,
                    'reason' => 'VACIA_CERCANA',
                ];
            }
        }

        return array_slice($suggestions, 0, $limit);
    }
}
