<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockByLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item' => [
                'id' => $this->item->id,
                'sku' => $this->item->sku,
                'name' => $this->item->name,
                'base_uom' => $this->item->baseUom?->code,
            ],
            'lot' => [
                'id' => $this->lot->id,
                'code' => $this->lot->code,
                'expires_at' => $this->lot->expires_at?->toDateString(),
            ],
            'status' => $this->status,
            'qty' => (float) $this->qty,
            'qty_available' => (float) $this->qty_available,
            'last_movement_at' => $this->last_movement_at?->toIso8601String(),
        ];
    }
}
