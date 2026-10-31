<?php

namespace App\Http\Requests;

use App\Services\MovieService;

class MoviePageRequest
{
    public static function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.MovieService::MAX_PAGE],
        ];
    }
}
