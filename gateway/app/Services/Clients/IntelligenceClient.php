<?php

namespace App\Services\Clients;

class IntelligenceClient extends BaseClient
{
    protected string $service = 'intelligence';

    public function weatherSuggestions(float $lat, float $lon): array
    {
        return $this->get('/api/suggestions/weather', ['lat' => $lat, 'lon' => $lon])['data'];
    }

    /** The event as an .ics file (raw text). Fields: docs/api/intelligence.md, "Calendar". */
    public function calendarIcs(array $event): string
    {
        return $this->postForFile('/api/calendar/ics', $event);
    }

    /** A Google Calendar "add event" URL for the same fields. */
    public function calendarGoogleLink(array $event): string
    {
        return $this->get('/api/calendar/google-link', $event)['data']['url'];
    }
}
