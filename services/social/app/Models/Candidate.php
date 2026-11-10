<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['movie_id', 'added_by'];

    public function movieNight(): BelongsTo
    {
        return $this->belongsTo(MovieNight::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
