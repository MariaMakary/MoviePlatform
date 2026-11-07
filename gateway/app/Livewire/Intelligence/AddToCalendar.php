<?php

namespace App\Livewire\Intelligence;

use App\Exceptions\ServiceUnavailable;
use App\Services\Clients\IntelligenceClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Add to calendar" for an upcoming movie: its release date as an all-day event, as an .ics download or a
 * Google Calendar link. Both go through Intelligence; the browser never calls it directly.
 */
class AddToCalendar extends Component
{
    #[Locked]
    public string $movieId;

    #[Locked]
    public string $title;

    /** Y-m-d */
    #[Locked]
    public string $releaseDate;

    #[Locked]
    public ?string $overview = null;

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

        // A redirect, not window.open: a popup opened after the request would be blocked.
        return $this->redirect($url);
    }

    private function event(): array
    {
        return array_filter([
            'title' => Str::limit('Release: '.$this->title, 255, ''),
            'description' => $this->overview ? Str::limit($this->overview, 4900) : null,
            'start' => $this->releaseDate,
            'all_day' => 1,
            'url' => route('movies.show', $this->movieId),
        ], fn ($value) => $value !== null);
    }
}
