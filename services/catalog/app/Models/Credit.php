<?php

namespace App\Models;

use App\Enums\CreditRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Credit extends Pivot
{
    use HasUuids;

    protected $table = 'movie_person';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'role' => CreditRole::class,
        'cast_order' => 'integer',
    ];
}
