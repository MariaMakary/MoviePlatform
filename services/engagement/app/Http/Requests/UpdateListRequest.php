<?php

namespace App\Http\Requests;

class UpdateListRequest
{
    public static function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
