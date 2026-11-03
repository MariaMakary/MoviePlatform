<?php

namespace App\Http\Requests;

use App\Services\MovieService;

class SearchMoviesRequest
{
    public static function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'genre' => ['sometimes', 'uuid'],
            'year' => ['sometimes', 'integer', 'between:1870,2100'],
            'sort' => ['sometimes', 'in:'.implode(',', MovieService::SEARCH_SORTS)],
            ...MoviePageRequest::rules(),
        ];
    }
}
