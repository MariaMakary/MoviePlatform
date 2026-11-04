<?php

namespace App\Models;

use App\Enums\SentimentLabel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** The sentiment of one Engagement review (review_id is Engagement's reviews.id, no foreign key). */
class SentimentResult extends Model
{
    use HasUuids;

    protected $fillable = ['review_id', 'label', 'score'];

    protected $casts = [
        'label' => SentimentLabel::class,
        'score' => 'float',
    ];
}
