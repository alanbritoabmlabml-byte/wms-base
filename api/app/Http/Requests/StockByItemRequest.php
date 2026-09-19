<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockByItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ];
    }
}
