<?php

namespace App\Http\Requests;

class StoreMessageRequest
{
    public static function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
        ];
    }
}
