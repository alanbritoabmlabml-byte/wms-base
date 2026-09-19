<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string'],
            'device_serial' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'device_model' => ['nullable', 'string', 'max:40'],
        ];
    }
}
