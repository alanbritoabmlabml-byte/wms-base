<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una linea del kardex con su saldo corrido. `running_balance` lo calcula el
 * controlador antes de serializar (no se puede derivar de la fila sola).
 */
class KardexEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'qty' => (float) $this->qty,
            'signed_qty' => (float) $this->signed_qty,
            'running_balance' => (float) $this->running_balance,
            'lot' => $this->lot?->code,
            'from_location' => $this->fromLocation?->code,
            'to_location' => $this->toLocation?->code,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'document_type' => $this->document_type,
            'document_id' => $this->document_id,
            'reason_code' => $this->reasonCode?->code,
            'user' => $this->user?->username,
            'device_id' => $this->device_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
