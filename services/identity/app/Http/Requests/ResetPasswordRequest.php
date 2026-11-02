<?php

namespace App\Http\Requests;

class ResetPasswordRequest
{
    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', EmailRules::NO_CONTROL_CHARACTERS, 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
