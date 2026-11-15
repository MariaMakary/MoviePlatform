<?php

namespace App\Livewire\Intelligence;

use App\Exceptions\ServiceUnavailable;
use App\Services\Clients\IntelligenceClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AddToCalendar extends Component
{
    #[Locked]
    public string $title;

    #[Locked]
    public string $start;

    #[Locked]
    public bool $allDay = false;

    #[Locked]
    public ?string $description = null;

    #[Locked]
    public ?string $location = null;

    #[Locked]
    public ?string $url = null;

    public ?string $error = null;

    public function render(): View
    {
        return view('livewire.intelligence.add-to-calendar');
    }

    public function download(IntelligenceClient $intelligence)
    {
        try {
            $ics = $intelligence->calendarIcs($this->event());
        } catch (ServiceUnavailable) {
            $this->error = 'Calendar is unavailable right now. Try again later.';

            return null;
        }

        $this->error = null;

        return response()->streamDownload(
            fn () => print ($ics),
            Str::slug($this->title).'.ics',
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }

    public function google(IntelligenceClient $intelligence)
    {
        try {
            $url = $intelligence->calendarGoogleLink($this->event());
        } catch (ServiceUnavailable) {
            $this->error = 'Calendar is unavailable right now. Try again later.';

            return null;
        }

        return $this->redirect($url);
    }

    private function event(): array
    {
        return array_filter([
            'title' => Str::limit($this->title, 255, ''),
            'description' => $this->description ? Str::limit($this->description, 4900) : null,
            'start' => $this->start,
            'all_day' => $this->allDay ? 1 : 0,
            'location' => $this->location ? Str::limit($this->location, 255, '') : null,
            'url' => $this->url,
        ], fn ($value) => $value !== null);
    }
}
