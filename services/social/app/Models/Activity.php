<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One feed entry, built from an Engagement event (task 1213). `subject_id` is the Engagement rating, review or list id
 * (the movie id for a watch, which has no id of its own); `data` holds what the feed shows (score, excerpt, title…).
 */
class Activity extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['event_id', 'user_id', 'type', 'subject_id', 'movie_id', 'data', 'created_at'];

    protected $casts = [
        'type' => ActivityType::class,
        'data' => 'array',
        'created_at' => 'datetime',
        'author_blocked' => 'boolean',
    ];

    /** Hides the activity of a user an admin blocked (user.blocked); user.unblocked shows it again. */
    public function scopeAuthorNotBlocked(Builder $query): Builder
    {
        return $query->where('activities.author_blocked', false);
    }

    /** Only what the author shows to others: each type needs its privacy setting on (no saved settings = all on). */
    public function scopeAllowedByPrivacy(Builder $query): Builder
    {
        return $query->select('activities.*')
            ->leftJoin('privacy_settings', 'privacy_settings.user_id', '=', 'activities.user_id')
            ->where(function (Builder $allowed) {
                foreach (ActivityType::cases() as $type) {
                    $allowed->orWhere(fn (Builder $query) => $query
                        ->where('activities.type', $type->value)
                        ->whereRaw('coalesce(privacy_settings.'.$type->privacyField().', true)'));
                }
            });
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('activities.created_at')->orderByDesc('activities.id');
    }
}
