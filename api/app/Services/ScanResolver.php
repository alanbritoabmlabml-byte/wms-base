<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Models\Lot;
use App\Models\StockBalance;
use App\Support\Gs1Parser;

/**
 * El endpoint mas usado del sistema: decide que es lo que el operador acaba de
 * escanear. El colector ya resolvio primero contra su cache local; aca llega solo
 * lo que no conocia o lo que necesita ocupacion en vivo.
 */
class ScanResolver
{
    public function __construct(private readonly Gs1Parser $gs1) {}

    public function resolve(string $code, int $warehouseId): array
    {
        $code = trim($code);

        // 1. GS1-128: trae GTIN + lote + vencimiento + cantidad en un solo disparo.
        if (Gs1Parser::looksLikeGs1($code)) {
            $resolved = $this->resolveGs1($code, $warehouseId);

            if ($resolved) {
                return $resolved;
            }
        }

        // 2. Ubicacion del almacen (por barcode impreso o por codigo).
        $location = Location::query()
            ->with('zone')
            ->where('warehouse_id', $warehouseId)
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('code', $code))
            ->first();

        if ($location) {
            return $this->locationPayload($location);
        }

        // 3. Codigo de item (EAN del proveedor, interno, de bulto...).
        $barcode = ItemBarcode::with(['item.baseUom', 'uom'])->where('barcode', $code)->first();

        if ($barcode) {
            return $this->itemPayload($barcode->item, [
                'barcode' => $barcode->barcode,
                'uom' => $barcode->uom?->code,
                'qty_per_scan' => (float) $barcode->qty_per_scan,
            ]);
        }

        // 4. SKU interno escrito a mano.
        $item = Item::with('baseUom')->where('sku', $code)->first();

        if ($item) {
            return $this->itemPayload($item, [
                'barcode' => $item->sku,
                'uom' => $item->baseUom?->code,
                'qty_per_scan' => 1.0,
            ]);
        }

        // 5. Etiqueta de lote.
        $lot = Lot::with('item.baseUom')->where('code', $code)->where('code', '!=', Lot::NONE)->first();

        if ($lot) {
            return [
                'kind' => 'LOT',
                'lot' => [
                    'id' => $lot->id,
                    'code' => $lot->code,
                    'expires_at' => $lot->expires_at?->toDateString(),
                ],
                'item' => $this->itemBlock($lot->item),
            ];
        }

        return ['kind' => 'UNKNOWN', 'code' => $code];
    }

    private function resolveGs1(string $code, int $warehouseId): ?array
    {
        $parsed = $this->gs1->parse($code);

        if ($parsed === []) {
            return null;
        }

        $gtin = $this->gs1->gtin($parsed);
        $item = null;
        $qtyPerScan = 1.0;

        if ($gtin) {
            // El GTIN puede estar guardado con o sin ceros a la izquierda.
            $candidates = array_unique([$gtin, ltrim($gtin, '0'), str_pad($gtin, 14, '0', STR_PAD_LEFT)]);

            $barcode = ItemBarcode::with(['item.baseUom', 'uom'])
                ->whereIn('barcode', $candidates)
                ->first();

            if ($barcode) {
                $item = $barcode->item;
                $qtyPerScan = (float) $barcode->qty_per_scan;
            }
        }

        $declared = $this->gs1->quantity($parsed);

        return [
            'kind' => 'GS1',
            'parsed' => $parsed,
            'item' => $item ? $this->itemBlock($item) : null,
            'lot_code' => $parsed['10'] ?? null,
            'expires_at' => $parsed['17'] ?? null,
            'qty' => $declared !== null ? $declared * $qtyPerScan : null,
        ];
    }

    private function locationPayload(Location $location): array
    {
        $occupancy = StockBalance::query()
            ->where('location_id', $location->id)
            ->where('qty', '>', 0)
            ->selectRaw('COUNT(DISTINCT item_id) as items, COALESCE(SUM(qty), 0) as total_qty')
            ->first();

        return [
            'kind' => 'LOCATION',
            'location' => [
                'id' => $location->id,
                'code' => $location->code,
                'zone' => $location->zone?->code,
                'is_active' => (bool) $location->is_active,
                'is_mixing_allowed' => (bool) $location->is_mixing_allowed,
                'occupancy' => [
                    'items' => (int) ($occupancy->items ?? 0),
                    'total_qty' => round((float) ($occupancy->total_qty ?? 0), 4),
                ],
            ],
        ];
    }

    private function itemPayload(Item $item, array $scanned): array
    {
        return [
            'kind' => 'ITEM',
            'item' => $this->itemBlock($item),
            'scanned' => $scanned,
        ];
    }

    private function itemBlock(Item $item): array
    {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'base_uom' => $item->baseUom?->code,
            'tracks_lot' => (bool) $item->tracks_lot,
            'tracks_expiry' => (bool) $item->tracks_expiry,
        ];
    }
}
