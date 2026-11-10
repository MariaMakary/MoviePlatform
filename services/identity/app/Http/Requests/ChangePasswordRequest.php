<?php

namespace App\Http\Requests;

class ChangePasswordRequest
{
    public static function rules(): array
    {
        return [
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
