<?php

namespace App\Http\Requests;

class VoteRequest
{
    public static function rules(): array
    {
        return [
            'candidate_id' => ['required', 'string', 'uuid'],
        ];
    }
}
