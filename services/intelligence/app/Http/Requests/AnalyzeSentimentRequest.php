<?php

namespace App\Http\Requests;

class AnalyzeSentimentRequest
{
    public static function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000', 'regex:/\S/'],
        ];
    }
}
