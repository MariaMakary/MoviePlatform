<?php

namespace App\Http\Requests;

class StoreHistoryRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'uuid'],
            'watched_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
