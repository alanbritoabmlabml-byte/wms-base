<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type,
            'status' => $this->status,
            'expected_at' => $this->expected_at?->toDateString(),
            'lines_total' => (int) $this->lines_total,
            'lines_done' => (int) $this->lines_done,
        ];
    }
}
