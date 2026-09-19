<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'base_uom' => $this->baseUom?->code,
            'decimals' => (int) ($this->baseUom?->decimals ?? 0),
            'tracks_lot' => (bool) $this->tracks_lot,
            'tracks_expiry' => (bool) $this->tracks_expiry,
            'barcodes' => $this->whenLoaded('barcodes', fn () => $this->barcodes->map(fn ($b) => [
                'barcode' => $b->barcode,
                'uom' => $b->uom?->code,
                'qty_per_scan' => (float) $b->qty_per_scan,
                'type' => $b->type,
            ])->values()),
        ];
    }
}
