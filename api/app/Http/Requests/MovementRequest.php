<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::in([
                'PUTAWAY', 'REUBICACION', 'PICKING', 'DESPACHO',
                'AJUSTE_POS', 'AJUSTE_NEG', 'CAMBIO_ESTADO',
            ])],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'lot_code' => ['nullable', 'string', 'max:40'],
            'from_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'to_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'status' => ['nullable', 'string', 'in:BUENO,OBSERVADO,CUARENTENA,DANADO'],
            'from_status' => ['nullable', 'string', 'in:BUENO,OBSERVADO,CUARENTENA,DANADO'],
            'to_status' => ['nullable', 'string', 'in:BUENO,OBSERVADO,CUARENTENA,DANADO'],
            'reason_code_id' => ['nullable', 'integer', 'exists:reason_codes,id'],
            'scanned_barcode' => ['nullable', 'string', 'max:64'],
            'occurred_at' => ['nullable', 'date'],
            'device_serial' => ['nullable', 'string', 'max:64'],
        ];
    }
}
