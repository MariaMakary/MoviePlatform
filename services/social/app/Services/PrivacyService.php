<?php

namespace App\Services;

use App\Models\PrivacySetting;

class PrivacyService
{
    /** The user's settings, or the defaults (not saved) when they never changed them. */
    public function for(string $userId): PrivacySetting
    {
        return PrivacySetting::find($userId) ?? new PrivacySetting(['user_id' => $userId]);
    }

    public function update(string $userId, array $settings): PrivacySetting
    {
        return PrivacySetting::updateOrCreate(['user_id' => $userId], $settings);
    }
}
