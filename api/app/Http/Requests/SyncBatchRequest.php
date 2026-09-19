<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_serial' => ['nullable', 'string', 'max:64'],
            'operations' => ['required', 'array', 'min:1', 'max:500'],
            'operations.*.endpoint' => ['required', 'string', 'max:120'],
            'operations.*.payload' => ['required', 'array'],
        ];
    }
}
