<?php

namespace App\Http\Requests;

use App\Models\PrivacySetting;

class UpdatePrivacyRequest
{
    /** All four are required: the request replaces the whole setting. */
    public static function rules(): array
    {
        return array_fill_keys(PrivacySetting::FIELDS, ['required', 'boolean']);
    }
}
