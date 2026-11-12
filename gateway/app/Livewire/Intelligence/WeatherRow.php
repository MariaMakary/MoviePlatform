<?php

namespace App\Livewire\Intelligence;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\IntelligenceClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WeatherRow extends Component
{
    use WithServiceFallback;

    #[Locked]
    public ?float $lat = null;

    #[Locked]
    public ?float $lon = null;

    #[Locked]
    public bool $usingFallback = false;

    public function locate(mixed $lat = null, mixed $lon = null): void
    {
        $valid = is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180;

        $this->usingFallback = ! $valid;
        $this->lat = $valid ? (float) $lat : CinemaMap::FALLBACK['lat'];
        $this->lon = $valid ? (float) $lon : CinemaMap::FALLBACK['lng'];
    }

    public function render(IntelligenceClient $intelligence): View
    {
        $suggestion = null;

        if ($this->lat !== null) {
            try {
                $suggestion = $this->fromService('weather', fn () => $intelligence->weatherSuggestions($this->lat, $this->lon));
            } catch (ServiceRequestException $e) {
                $this->unavailable['weather'] = $e->service;
            }
        }

        return view('livewire.intelligence.weather-row', [
            'suggestion' => $suggestion,
            'fallbackCity' => CinemaMap::FALLBACK['name'],
        ]);
    }
}
