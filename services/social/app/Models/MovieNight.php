<?php

namespace App\Models;

use App\Enums\MovieNightStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovieNight extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['host_id', 'title', 'description', 'scheduled_at', 'location', 'meeting_link'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'status' => MovieNightStatus::class,
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    public function scopeWithParticipant(Builder $query, string $userId): Builder
    {
        return $query->whereIn('id', Participant::select('movie_night_id')->where('user_id', $userId));
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
