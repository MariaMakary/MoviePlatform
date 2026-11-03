<?php

namespace App\Http\Requests;

class SearchUsersRequest
{
    public static function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }
}
