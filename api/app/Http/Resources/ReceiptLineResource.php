<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_no' => (int) $this->line_no,
            'item' => [
                'id' => $this->item->id,
                'sku' => $this->item->sku,
                'name' => $this->item->name,
                'base_uom' => $this->item->baseUom?->code,
                'tracks_lot' => (bool) $this->item->tracks_lot,
            ],
            'lot_code' => $this->lot_code,
            'qty_expected' => (float) $this->qty_expected,
            'qty_received' => (float) $this->qty_received,
            'uom' => $this->uom?->code,
            'status' => $this->status,
            'suggested_locations' => $this->suggested_locations ?? [],
        ];
    }
}
