<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class DisplayTime
{
    public static function timezone(): string
    {
        return config('app.display_timezone');
    }

    public static function fromService(?string $utc): ?CarbonImmutable
    {
        if ($utc === null || $utc === '') {
            return null;
        }

        return CarbonImmutable::parse($utc, 'UTC')->setTimezone(self::timezone());
    }

    public static function toService(?string $local): ?string
    {
        if ($local === null || $local === '') {
            return null;
        }

        return CarbonImmutable::parse($local, self::timezone())->utc()->toIso8601ZuluString();
    }
}
