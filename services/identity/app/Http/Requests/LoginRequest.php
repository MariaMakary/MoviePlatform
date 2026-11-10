<?php

namespace App\Http\Requests;

class LoginRequest
{
    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', EmailRules::NO_CONTROL_CHARACTERS, 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
