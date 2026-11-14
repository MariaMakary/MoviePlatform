<?php

namespace App\Http\Requests;

class DiscoverMoviesRequest
{
    public const DEFAULT_LIMIT = 10;

    public static function rules(): array
    {
        return [
            'genres' => ['required', 'array', 'max:10'],
            'genres.*' => ['string', 'max:50'],
            'exclude_ids' => ['array', 'max:'.MovieIdsRequest::MAX_IDS],
            'exclude_ids.*' => ['uuid'],
            'limit' => ['integer', 'between:1,50'],
        ];
    }
}
