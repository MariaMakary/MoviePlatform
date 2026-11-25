<?php

namespace App\Http\Requests;

class UpdateMovieRequest
{
    /** TMDB has 19 movie genres; the cap only stops a huge list (one `exists` query per id). */
    public const MAX_GENRES = 20;

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'overview' => ['present', 'nullable', 'string', 'max:5000'],
            'poster_path' => ['present', 'nullable', 'string', 'max:255', 'regex:#^/[A-Za-z0-9_.-]+$#'],
            'release_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'runtime' => ['present', 'nullable', 'integer', 'between:1,1000'],
            'genre_ids' => ['present', 'array', 'max:'.self::MAX_GENRES],
            'genre_ids.*' => ['uuid', 'distinct', 'exists:genres,id'],
        ];
    }

    public static function messages(): array
    {
        return [
            'poster_path.regex' => 'The poster path must be a TMDB image path such as /abc123.jpg.',
            'genre_ids.*.exists' => 'This genre does not exist.',
        ];
    }
}
