<?php

namespace App\Http\Requests;

class ChangePasswordRequest
{
    public static function rules(): array
    {
        return [
            'current_password' => ['nullable', 'string', 'max:'.PasswordRules::MAX_CHECKED_LENGTH],
            'password' => PasswordRules::NEW,
        ];
    }
}
