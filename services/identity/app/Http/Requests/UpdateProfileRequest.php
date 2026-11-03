<?php

namespace App\Http\Requests;

class UpdateProfileRequest
{
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }
}
