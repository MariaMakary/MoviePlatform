<?php

namespace Tests;

class CalendarTest extends TestCase
{
    private function ics(array $data): string
    {
        $this->json('POST', '/api/calendar/ics', $data)->seeStatusCode(200);

        return $this->response->getContent();
    }

    /** Joins folded lines back (RFC 5545 3.1) so assertions can read whole properties. */
    private function unfold(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }

    public function test_ics_is_a_valid_calendar_file(): void
    {
        $ics = $this->ics(['title' => 'Dune', 'start' => '2026-11-03T18:00:00Z', 'end' => '2026-11-03T20:45:00Z']);

        $this->assertStringStartsWith('text/calendar', $this->response->headers->get('Content-Type'));
        $this->assertStringContainsString('filename="dune.ics"', $this->response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        $this->assertStringEndsWith("END:VEVENT\r\nEND:VCALENDAR\r\n", $ics);
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $ics), 'Every line must end with CRLF.');
        $this->assertMatchesRegularExpression("/\r\nUID:[0-9a-f-]{36}@movieplatform\r\n/", $ics);
        $this->assertMatchesRegularExpression("/\r\nDTSTAMP:\d{8}T\d{6}Z\r\n/", $ics);
        $this->assertStringContainsString("\r\nDTSTART:20261103T180000Z\r\nDTEND:20261103T204500Z\r\n", $ics);
        $this->assertStringContainsString("\r\nSUMMARY:Dune\r\n", $ics);
    }

    public function test_ics_converts_times_to_utc_and_defaults_to_two_hours(): void
    {
        $ics = $this->ics(['title' => 'Movie night', 'start' => '2026-11-03T20:00:00+02:00']);

        $this->assertStringContainsString("\r\nDTSTART:20261103T180000Z\r\nDTEND:20261103T200000Z\r\n", $ics);
    }

    public function test_ics_escapes_text_values(): void
    {
        $ics = $this->ics([
            'title' => 'Dune, Part Two; IMAX',
            'description' => "Bring snacks\nSeats: A1, A2\r\nPath C:\\movies",
            'location' => 'Cinema City; Beirut, Lebanon',
            'url' => 'https://example.com/movies?id=1,2;3',
            'start' => '2026-11-03T18:00:00Z',
        ]);

        // Single quotes: every backslash below is a literal backslash in the file.
        $this->assertStringContainsString("\r\n".'SUMMARY:Dune\, Part Two\; IMAX'."\r\n", $ics);
        $this->assertStringContainsString("\r\n".'DESCRIPTION:Bring snacks\nSeats: A1\, A2\nPath C:\\\\movies'."\r\n", $ics);
        $this->assertStringContainsString("\r\n".'LOCATION:Cinema City\; Beirut\, Lebanon'."\r\n", $ics);
        $this->assertStringContainsString("\r\nURL:https://example.com/movies?id=1,2;3\r\n", $ics);
    }

    public function test_ics_all_day_event_uses_dates_with_exclusive_end(): void
    {
        $ics = $this->ics(['title' => 'Release', 'start' => '2026-11-03', 'all_day' => true]);

        $this->assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261103\r\nDTEND;VALUE=DATE:20261104\r\n", $ics);
    }

    public function test_ics_multi_day_all_day_event_ends_the_day_after_the_last_day(): void
    {
        $ics = $this->ics(['title' => 'Festival', 'start' => '2026-11-03', 'end' => '2026-11-05', 'all_day' => true]);

        $this->assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261103\r\nDTEND;VALUE=DATE:20261106\r\n", $ics);
    }

    public function test_ics_all_day_keeps_the_given_date_whatever_the_offset(): void
    {
        $ics = $this->ics(['title' => 'Release', 'start' => '2026-11-03T00:30:00+03:00', 'all_day' => true]);

        $this->assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261103\r\n", $ics);
    }

    public function test_ics_folds_long_lines_without_splitting_characters(): void
    {
        $description = str_repeat('Une soirée cinéma, ', 20);
        $ics = $this->ics(['title' => 'Long', 'description' => $description, 'start' => '2026-11-03T18:00:00Z']);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'A fold split a UTF-8 character.');
        }
        $this->assertStringContainsString(
            "\r\nDESCRIPTION:".str_replace(',', '\,', $description)."\r\n",
            $this->unfold($ics),
        );
    }

    public function test_ics_validation_errors_are_json(): void
    {
        $this->json('POST', '/api/calendar/ics', ['start' => 'not a date', 'url' => 'nope'])
            ->seeStatusCode(422)
            ->seeJsonStructure(['message', 'errors' => ['title', 'start', 'url']]);

        $this->assertStringStartsWith('application/json', $this->response->headers->get('Content-Type'));
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->json('POST', '/api/calendar/ics', ['title' => 'Dune', 'start' => '2026-11-03T18:00:00Z', 'end' => '2026-11-02T18:00:00Z'])
            ->seeStatusCode(422)
            ->seeJsonStructure(['errors' => ['end']]);
    }

    public function test_google_link_prefills_the_event(): void
    {
        $this->get('/api/calendar/google-link?'.http_build_query([
            'title' => 'Dune, Part Two',
            'description' => 'Bring snacks',
            'start' => '2026-11-03T18:00:00Z',
            'end' => '2026-11-03T20:45:00Z',
            'location' => 'Cinema City',
            'url' => 'https://example.com/movies/1',
        ]))->seeStatusCode(200);

        $url = $this->response->getData(true)['data']['url'];
        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame([
            'action' => 'TEMPLATE',
            'text' => 'Dune, Part Two',
            'dates' => '20261103T180000Z/20261103T204500Z',
            'details' => "Bring snacks\n\nhttps://example.com/movies/1",
            'location' => 'Cinema City',
        ], $query);
    }

    public function test_google_link_all_day_uses_dates(): void
    {
        $this->get('/api/calendar/google-link?title=Release&start=2026-11-03&all_day=1')->seeStatusCode(200);

        parse_str(parse_url($this->response->getData(true)['data']['url'], PHP_URL_QUERY), $query);
        $this->assertSame('20261103/20261104', $query['dates']);
    }

    public function test_google_link_validation_errors_are_json(): void
    {
        $this->get('/api/calendar/google-link?start=2026-11-03')
            ->seeStatusCode(422)
            ->seeJsonStructure(['message', 'errors' => ['title']]);
    }
}
