<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Credit extends Pivot
{
    use HasUuids;

    public const ROLE_CAST = 'cast';

    public const ROLE_DIRECTOR = 'director';

    protected $table = 'movie_person';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'cast_order' => 'integer',
    ];
}
