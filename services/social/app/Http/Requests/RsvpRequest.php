<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

class RsvpRequest
{
    public static function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([ParticipantStatus::Accepted->value, ParticipantStatus::Declined->value])],
        ];
    }
}
