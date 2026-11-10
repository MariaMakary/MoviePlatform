<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Named MovieList because `List` is a reserved word in PHP.
class MovieList extends Model
{
    use HasUuids;

    protected $table = 'lists';

    protected $fillable = ['user_id', 'title', 'description', 'is_public'];

    protected $casts = [
        'is_public' => 'boolean',
        'author_blocked' => 'boolean',
    ];

    protected $attributes = [
        'is_public' => false,
        'author_blocked' => false,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }

    public function scopeOwnedBy(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('author_blocked', false);
    }

    public function isVisibleTo(?string $userId): bool
    {
        return $this->user_id === $userId || ($this->is_public && ! $this->author_blocked);
    }
}
