<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['user_id', 'candidate_id'];

    public function movieNight(): BelongsTo
    {
        return $this->belongsTo(MovieNight::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
