<?php

namespace App\Http\Requests;

class StoreMovieNightRequest
{
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'location' => ['nullable', 'required_without:meeting_link', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'required_without:location', 'string', 'url:http,https', 'max:255'],
        ];
    }

    public static function messages(): array
    {
        return [
            'scheduled_at.after' => 'The movie night must be in the future.',
            'location.required_without' => 'Give a location or a meeting link.',
            'meeting_link.required_without' => 'Give a location or a meeting link.',
        ];
    }
}
