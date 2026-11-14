<?php

namespace App\Services\Clients;

class IntelligenceClient extends BaseClient
{
    protected string $service = 'intelligence';

    /** [{movie_id, score, reason}], best first; [] for a user without a taste profile yet. */
    public function recommendations(string $userId): array
    {
        return $this->get('/api/recommendations/'.rawurlencode($userId))['data'];
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
