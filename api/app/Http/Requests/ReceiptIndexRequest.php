<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            // Lista separada por comas: "ABIERTA,EN_PROCESO".
            'status' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** @return array<int,string> */
    public function statuses(): array
    {
        $raw = $this->input('status');

        if (! $raw) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
