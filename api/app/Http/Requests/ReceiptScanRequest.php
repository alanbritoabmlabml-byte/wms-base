<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Todo POST que mueva stock exige client_uuid. Sin el, 422.
            'client_uuid' => ['required', 'uuid'],
            'receipt_line_id' => ['required', 'integer', 'exists:receipt_lines,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'lot_code' => ['nullable', 'string', 'max:40'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'status' => ['nullable', 'string', 'in:BUENO,OBSERVADO,CUARENTENA,DANADO'],
            'scanned_barcode' => ['nullable', 'string', 'max:64'],
            'scanned_uom' => ['nullable', 'string', 'max:10'],
            'occurred_at' => ['nullable', 'date'],
            'device_serial' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
