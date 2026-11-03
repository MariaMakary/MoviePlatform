<?php

namespace App\Http\Requests;

class UpdateReviewRequest
{
    public static function rules(): array
    {
        return [
            // regex: at least one character that is not a space, so a blank review is refused.
            'body' => ['required', 'string', 'between:10,2000', 'regex:/\S/'],
        ];
    }
}
