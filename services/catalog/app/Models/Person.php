<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Person extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'people';

    protected $fillable = ['tmdb_id', 'name', 'profile_path', 'biography', 'birthday', 'known_for'];

    protected $casts = [
        'tmdb_id' => 'integer',
        'birthday' => 'date',
    ];

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_person')
            ->using(Credit::class)
            ->withPivot('id', 'role', 'character', 'cast_order');
    }
}
