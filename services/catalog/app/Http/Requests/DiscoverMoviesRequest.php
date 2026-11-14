<?php

namespace App\Http\Requests;

class DiscoverMoviesRequest
{
    public const DEFAULT_LIMIT = 10;

    public static function rules(): array
    {
        return [
            // Genre names, or Catalog genre ids (taste profiles store ids); at least one of the two.
            'genres' => ['required_without:genre_ids', 'array', 'max:10'],
            'genres.*' => ['string', 'max:50'],
            'genre_ids' => ['required_without:genres', 'array', 'max:10'],
            'genre_ids.*' => ['uuid'],
            'exclude_ids' => ['array', 'max:'.MovieIdsRequest::MAX_IDS],
            'exclude_ids.*' => ['uuid'],
            'limit' => ['integer', 'between:1,50'],
        ];
    }
}
