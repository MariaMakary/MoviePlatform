<?php

namespace App\Models;

use App\Enums\SentimentLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'movie_id', 'body'];

    protected $casts = [
        'sentiment_label' => SentimentLabel::class,
        'sentiment_score' => 'float',
        'is_hidden' => 'boolean',
        'author_blocked' => 'boolean',
    ];

    protected $attributes = [
        'is_hidden' => false,
        'author_blocked' => false,
    ];

    /** Shown in public lists only when neither an admin (is_hidden) nor user.blocked (author_blocked) hid it. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false)->where('author_blocked', false);
    }

    /** Adds `author_score`: the author's rating of the same movie, or null. */
    public function scopeWithAuthorScore(Builder $query): Builder
    {
        return $query->addSelect([
            'author_score' => Rating::select('score')
                ->whereColumn('ratings.user_id', 'reviews.user_id')
                ->whereColumn('ratings.movie_id', 'reviews.movie_id'),
        ]);
    }
}
