<?php

namespace App\Livewire\Intelligence;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cinemas near me')]
class CinemaMap extends Component
{
    /** Where the map opens when the browser's location is refused or unavailable. */
    public const FALLBACK = ['lat' => 33.8938, 'lng' => 35.5018, 'name' => 'Beirut'];

    public function render(): View
    {
        return view('livewire.intelligence.cinema-map', ['fallback' => self::FALLBACK]);
    }
}
