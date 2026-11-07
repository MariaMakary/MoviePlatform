<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a user shows on their public profile. No row means the defaults (everything shown). */
class PrivacySetting extends Model
{
    public const FIELDS = ['show_reviews', 'show_ratings', 'show_lists', 'show_history'];

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $fillable = ['user_id', ...self::FIELDS];

    protected $attributes = [
        'show_reviews' => true,
        'show_ratings' => true,
        'show_lists' => true,
        'show_history' => true,
    ];

    protected $casts = [
        'show_reviews' => 'boolean',
        'show_ratings' => 'boolean',
        'show_lists' => 'boolean',
        'show_history' => 'boolean',
    ];
}
