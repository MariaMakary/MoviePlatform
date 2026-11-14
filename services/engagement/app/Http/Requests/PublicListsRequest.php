<?php

namespace App\Http\Requests;

class PublicListsRequest
{
    public static function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:150'],
        ];
    }
}
