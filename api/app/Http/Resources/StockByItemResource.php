<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockByItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'location' => [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'zone' => $this->location->zone?->code,
                'sort_seq' => (int) $this->location->sort_seq,
            ],
            'lot' => [
                'id' => $this->lot->id,
                'code' => $this->lot->code,
                'expires_at' => $this->lot->expires_at?->toDateString(),
            ],
            'status' => $this->status,
            'qty' => (float) $this->qty,
            'qty_available' => (float) $this->qty_available,
        ];
    }
}
