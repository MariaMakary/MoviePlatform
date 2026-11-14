<?php

namespace App\Http\Requests;

class WeatherSuggestionRequest
{
    public static function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
