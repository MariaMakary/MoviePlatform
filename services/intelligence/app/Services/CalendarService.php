<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Builds "add to calendar" exports of one event: an RFC 5545 .ics file and a Google Calendar link.
 */
class CalendarService
{
    /** Length of a timed event sent without an end (about one movie). */
    private const DEFAULT_DURATION_MINUTES = 120;

    /** RFC 5545 3.1: content lines are at most 75 octets, longer ones are folded. */
    private const LINE_LIMIT = 75;

    private const GOOGLE_URL = 'https://calendar.google.com/calendar/render';

    public function ics(array $event): string
    {
        [$start, $end, $allDay] = $this->period($event);
        $dateParam = $allDay ? ';VALUE=DATE' : '';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MoviePlatform//Intelligence//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.Str::uuid().'@movieplatform',
            'DTSTAMP:'.CarbonImmutable::now('UTC')->format('Ymd\THis\Z'),
            "DTSTART{$dateParam}:{$start}",
            "DTEND{$dateParam}:{$end}",
            'SUMMARY:'.$this->escape($event['title']),
        ];

        if (! empty($event['description'])) {
            $lines[] = 'DESCRIPTION:'.$this->escape($event['description']);
        }
        if (! empty($event['location'])) {
            $lines[] = 'LOCATION:'.$this->escape($event['location']);
        }
        if (! empty($event['url'])) {
            // URL is a URI value, not TEXT: it is not escaped (RFC 5545 3.8.4.6).
            $lines[] = 'URL:'.$event['url'];
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line) => $this->fold($line), $lines))."\r\n";
    }

    public function googleLink(array $event): string
    {
        [$start, $end] = $this->period($event);

        $details = implode("\n\n", array_filter([$event['description'] ?? null, $event['url'] ?? null]));

        $params = array_filter([
            'action' => 'TEMPLATE',
            'text' => $event['title'],
            'dates' => "{$start}/{$end}",
            'details' => $details,
            'location' => $event['location'] ?? null,
        ]);

        return self::GOOGLE_URL.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function filename(array $event): string
    {
        return (Str::slug($event['title']) ?: 'event').'.ics';
    }

    /**
     * Start and end formatted for both exports. All-day events use dates with an exclusive end (the day after the
     * last day); timed events use UTC date-times.
     *
     * @return array{0: string, 1: string, 2: bool}
     */
    private function period(array $event): array
    {
        $allDay = (bool) ($event['all_day'] ?? false);
        $start = CarbonImmutable::parse($event['start']);
        $end = empty($event['end']) ? null : CarbonImmutable::parse($event['end']);

        if ($allDay) {
            $end = ($end ?? $start)->startOfDay()->addDay();

            return [$start->format('Ymd'), $end->format('Ymd'), true];
        }

        $start = $start->utc();
        $end = $end?->utc() ?? $start->addMinutes(self::DEFAULT_DURATION_MINUTES);

        return [$start->format('Ymd\THis\Z'), $end->format('Ymd\THis\Z'), false];
    }

    /** RFC 5545 3.3.11: escape backslashes, semicolons, commas and newlines in TEXT values. */
    private function escape(string $text): string
    {
        return strtr($text, [
            '\\' => '\\\\',
            ';' => '\;',
            ',' => '\,',
            "\r\n" => '\n',
            "\r" => '\n',
            "\n" => '\n',
        ]);
    }

    /** RFC 5545 3.1: split lines longer than 75 octets with CRLF + space, never inside a UTF-8 character. */
    private function fold(string $line): string
    {
        $folded = '';
        $limit = self::LINE_LIMIT;

        while (strlen($line) > $limit) {
            $cut = $limit;
            while ((ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            $folded .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
            $limit = self::LINE_LIMIT - 1; // the leading space counts
        }

        return $folded.$line;
    }
}
