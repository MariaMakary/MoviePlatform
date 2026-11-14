<?php

namespace App\Enums;

enum WeatherCode: int
{
    case Clear = 0;
    case MainlyClear = 1;
    case PartlyCloudy = 2;
    case Overcast = 3;
    case Fog = 45;
    case RimeFog = 48;
    case LightDrizzle = 51;
    case Drizzle = 53;
    case DenseDrizzle = 55;
    case LightFreezingDrizzle = 56;
    case FreezingDrizzle = 57;
    case LightRain = 61;
    case Rain = 63;
    case HeavyRain = 65;
    case LightFreezingRain = 66;
    case FreezingRain = 67;
    case LightSnow = 71;
    case Snow = 73;
    case HeavySnow = 75;
    case SnowGrains = 77;
    case LightShowers = 80;
    case Showers = 81;
    case HeavyShowers = 82;
    case LightSnowShowers = 85;
    case SnowShowers = 86;
    case Thunderstorm = 95;
    case ThunderstormWithHail = 96;
    case ThunderstormWithHeavyHail = 99;

    public function phrase(): string
    {
        return match ($this) {
            self::Clear => 'Clear sky',
            self::MainlyClear => 'Mainly clear',
            self::PartlyCloudy => 'Partly cloudy',
            self::Overcast => 'Overcast',
            self::Fog => 'Fog',
            self::RimeFog => 'Freezing fog',
            self::LightDrizzle => 'Light drizzle',
            self::Drizzle => 'Drizzle',
            self::DenseDrizzle => 'Dense drizzle',
            self::LightFreezingDrizzle => 'Light freezing drizzle',
            self::FreezingDrizzle => 'Freezing drizzle',
            self::LightRain => 'Light rain',
            self::Rain => 'Rain',
            self::HeavyRain => 'Heavy rain',
            self::LightFreezingRain => 'Light freezing rain',
            self::FreezingRain => 'Freezing rain',
            self::LightSnow => 'Light snow',
            self::Snow => 'Snow',
            self::HeavySnow => 'Heavy snow',
            self::SnowGrains => 'Snow grains',
            self::LightShowers => 'Light showers',
            self::Showers => 'Showers',
            self::HeavyShowers => 'Heavy showers',
            self::LightSnowShowers => 'Light snow showers',
            self::SnowShowers => 'Snow showers',
            self::Thunderstorm => 'Thunderstorm',
            self::ThunderstormWithHail => 'Thunderstorm with hail',
            self::ThunderstormWithHeavyHail => 'Thunderstorm with heavy hail',
        };
    }
}
