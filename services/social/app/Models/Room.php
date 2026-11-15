<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['movie_id', 'name'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
