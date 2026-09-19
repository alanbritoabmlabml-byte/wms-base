<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanResolveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'code' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'in:RECEPCION,PICKING,CONTEO,AJUSTE,TRASPASO,GENERAL'],
        ];
    }
}
