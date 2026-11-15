<?php

namespace App\Http\Requests;

class FinalizeRequest
{
    public static function rules(): array
    {
        return [
            'candidate_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
