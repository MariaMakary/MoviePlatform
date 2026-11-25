<?php

namespace App\Http\Requests;

class CalendarEventRequest
{
    // Laravel's url rule lets a trailing line break through, and the ICS URL line is not escaped (a URI, not TEXT).
    private const NO_CONTROL_CHARACTERS = 'not_regex:/[\x00-\x1F\x7F]/';

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'all_day' => ['sometimes', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', self::NO_CONTROL_CHARACTERS, 'url:http,https', 'max:2048'],
        ];
    }
}
