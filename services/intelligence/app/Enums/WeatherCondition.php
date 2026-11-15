<?php

namespace App\Enums;

enum WeatherCondition: string
{
    case Clear = 'clear';
    case Clouds = 'clouds';
    case Rain = 'rain';
    case Storm = 'storm';
    case Snow = 'snow';

    public static function fromCode(int $code): self
    {
        return match ($code) {
            0, 1, 2 => self::Clear,
            3, 45, 48 => self::Clouds,
            51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82 => self::Rain,
            95, 96, 99 => self::Storm,
            71, 73, 75, 77, 85, 86 => self::Snow,
            default => self::Clouds,
        };
    }

    public function genres(): array
    {
        return match ($this) {
            self::Rain => ['Drama', 'Romance'],
            self::Clear => ['Comedy', 'Adventure'],
            self::Snow => ['Family', 'Animation'],
            self::Clouds => ['Mystery', 'Thriller'],
            self::Storm => ['Horror'],
        };
    }
}
