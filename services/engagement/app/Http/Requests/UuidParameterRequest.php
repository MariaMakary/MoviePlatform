<?php

namespace App\Http\Requests;

/** A UUID from the URL path, merged into the request under $name before validating it. */
class UuidParameterRequest
{
    public static function rules(string $name): array
    {
        return [
            $name => ['required', 'uuid'],
        ];
    }
}
