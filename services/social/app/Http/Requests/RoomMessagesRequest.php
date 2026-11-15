<?php

namespace App\Http\Requests;

class RoomMessagesRequest
{
    public static function rules(): array
    {
        return [
            'before' => ['sometimes', 'string', 'uuid'],
        ];
    }
}
