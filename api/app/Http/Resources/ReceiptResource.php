<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type,
            'status' => $this->status,
            'warehouse_id' => $this->warehouse_id,
            'external_ref' => $this->external_ref,
            'supplier_name' => $this->supplier_name,
            'expected_at' => $this->expected_at?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'lines' => ReceiptLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
