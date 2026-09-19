<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'zone' => $this->zone?->code,
            'zone_type' => $this->zone?->type,
            'sort_seq' => (int) $this->sort_seq,
            'is_active' => (bool) $this->is_active,
            'is_mixing_allowed' => (bool) $this->is_mixing_allowed,
        ];
    }
}
