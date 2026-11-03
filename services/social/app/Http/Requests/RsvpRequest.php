<?php

namespace App\Http\Requests;

use App\Enums\ParticipantStatus;
use Illuminate\Validation\Rule;

class RsvpRequest
{
    public static function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([ParticipantStatus::Accepted->value, ParticipantStatus::Declined->value])],
        ];
    }
}
