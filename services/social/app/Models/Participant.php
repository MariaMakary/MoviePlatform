<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

class Participant extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['user_id', 'status', 'responded_at'];

    protected $casts = [
        'status' => ParticipantStatus::class,
        'responded_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function movieNight(): BelongsTo
    {
        return $this->belongsTo(MovieNight::class);
    }
}
