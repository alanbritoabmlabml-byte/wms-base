<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:200', 'required_if:force,true'],
        ];
    }
}
