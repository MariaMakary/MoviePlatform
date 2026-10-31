<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;

class RegisterRequest
{
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', function (string $attribute, string $value, Closure $fail) {
                if (User::where('email', User::normalizeEmail($value))->exists()) {
                    $fail('The email has already been taken.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
