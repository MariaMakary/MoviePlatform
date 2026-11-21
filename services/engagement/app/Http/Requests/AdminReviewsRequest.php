<?php

namespace App\Http\Requests;

use App\Enums\SentimentLabel;
use Illuminate\Validation\Rule;

/** Filters of GET /api/admin/reviews. An empty value (`?sentiment=`) means no filter. */
class AdminReviewsRequest
{
    public static function rules(): array
    {
        return [
            'sentiment' => ['sometimes', Rule::enum(SentimentLabel::class)],
            'movie_id' => ['sometimes', 'uuid'],
            'hidden' => ['sometimes', 'in:true,false,1,0'],
        ];
    }
}
