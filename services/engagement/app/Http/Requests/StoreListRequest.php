<?php

namespace App\Http\Requests;

class StoreListRequest
{
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
