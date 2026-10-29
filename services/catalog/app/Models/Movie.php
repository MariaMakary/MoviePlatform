<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Movie extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tmdb_id', 'title', 'overview', 'poster_path', 'backdrop_path', 'release_date', 'runtime',
        'vote_average', 'popularity', 'trailer_key', 'is_edited', 'synced_at',
    ];

    protected $casts = [
        'tmdb_id' => 'integer',
        'release_date' => 'date',
        'runtime' => 'integer',
        'vote_average' => 'decimal:1',
        'popularity' => 'float',
        'is_edited' => 'boolean',
        'synced_at' => 'datetime',
    ];

    protected $attributes = [
        'popularity' => 0,
        'is_edited' => false,
    ];

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'movie_genre');
    }

    public function cast(): BelongsToMany
    {
        return $this->credits(Credit::ROLE_CAST)->orderByPivot('cast_order');
    }

    public function directors(): BelongsToMany
    {
        return $this->credits(Credit::ROLE_DIRECTOR);
    }

    private function credits(string $role): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'movie_person')
            ->using(Credit::class)
            ->withPivot('id', 'character', 'cast_order')
            ->withPivotValue('role', $role);
    }
}
