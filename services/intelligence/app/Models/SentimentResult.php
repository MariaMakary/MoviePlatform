<?php

namespace App\Models;

use App\Enums\SentimentLabel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SentimentResult extends Model
{
    use HasUuids;

    protected $fillable = ['review_id', 'label', 'score'];

    protected $casts = [
        'label' => SentimentLabel::class,
        'score' => 'float',
    ];
}
