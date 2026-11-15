<?php

namespace App\Http\Requests;

class CandidateRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'string', 'uuid'],
        ];
    }
}
