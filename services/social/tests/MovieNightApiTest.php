<?php

namespace Tests;

use App\Enums\ParticipantStatus;
use App\Models\MovieNight;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieNightApiTest extends TestCase
{
    use DatabaseTransactions;

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MARIA = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const INCEPTION = '0193a7b0-4444-7aaa-8bbb-000000000010';

    private const ARRIVAL = '0193a7b0-4444-7aaa-8bbb-000000000011';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
        Carbon::setTestNow(Carbon::parse('2026-11-03T18:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId = self::HOST): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Test User', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function create(array $fields = [], string $userId = self::HOST): void
    {
        $this->json('POST', '/api/movie-nights', [
            'title' => 'Friday sci-fi',
            'scheduled_at' => '2026-11-07T19:00:00Z',
            'location' => 'Maria\'s place',
            ...$fields,
        ], $this->auth($userId));
    }

    private function night(string $hostId, string $scheduledAt = '2026-11-10T19:00:00Z'): MovieNight
    {
        $night = MovieNight::factory()->create(['host_id' => $hostId, 'scheduled_at' => Carbon::parse($scheduledAt)]);
        $night->participants()->create(['user_id' => $hostId, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);

        return $night;
    }

    private function errors(): array
    {
        return $this->response->getData(true)['errors'] ?? [];
    }

    public function test_creating_a_night_returns_it_with_the_host_as_accepted_participant(): void
    {
        $this->create(['description' => '  Bring snacks.  ', 'meeting_link' => 'https://meet.example.com/abc']);

        $this->seeStatusCode(201);
        $data = $this->response->getData(true)['data'];
        $night = MovieNight::findOrFail($data['id']);

        $this->assertSame([
            'id' => $night->id,
            'host_id' => self::HOST,
            'title' => 'Friday sci-fi',
            'description' => 'Bring snacks.',
            'scheduled_at' => '2026-11-07T19:00:00Z',
            'location' => 'Maria\'s place',
            'meeting_link' => 'https://meet.example.com/abc',
            'status' => 'open',
            'winner_movie_id' => null,
            'my_status' => 'accepted',
            'created_at' => '2026-11-03T18:00:00Z',
            'participants' => [
                ['user_id' => self::HOST, 'status' => 'accepted', 'is_host' => true, 'responded_at' => '2026-11-03T18:00:00Z'],
            ],
            'candidates' => [],
            'votes_cast' => 0,
        ], $data);
    }

    public function test_a_date_with_an_offset_is_stored_in_utc(): void
    {
        $this->create(['scheduled_at' => '2026-11-07T21:00:00+02:00']);

        $this->seeStatusCode(201)->seeJson(['scheduled_at' => '2026-11-07T19:00:00Z']);
    }

    public function test_a_location_or_a_meeting_link_is_enough(): void
    {
        $this->create(['location' => null, 'meeting_link' => 'https://meet.example.com/abc']);
        $this->seeStatusCode(201)->seeJson(['location' => null]);

        $this->create(['location' => 'Cinema', 'meeting_link' => null]);
        $this->seeStatusCode(201)->seeJson(['meeting_link' => null]);
    }

    public function test_a_past_date_gets_422(): void
    {
        $this->create(['scheduled_at' => '2026-11-03T17:59:00Z']);

        $this->seeStatusCode(422);
        $this->assertSame(['The movie night must be in the future.'], $this->errors()['scheduled_at']);
        $this->assertSame(0, MovieNight::count());
    }

    public function test_no_location_and_no_meeting_link_gets_422(): void
    {
        $this->create(['location' => '   ']);

        $this->seeStatusCode(422);
        $this->assertSame(['Give a location or a meeting link.'], $this->errors()['location']);
        $this->assertSame(['Give a location or a meeting link.'], $this->errors()['meeting_link']);
    }

    public function test_the_fields_are_validated(): void
    {
        $this->create([
            'title' => str_repeat('a', 101),
            'description' => str_repeat('b', 2001),
            'scheduled_at' => 'next friday-ish',
            'location' => str_repeat('c', 256),
            'meeting_link' => 'javascript:alert(1)',
        ]);

        $this->seeStatusCode(422);
        $this->assertEqualsCanonicalizing(['title', 'description', 'scheduled_at', 'location', 'meeting_link'], array_keys($this->errors()));

        $this->create(['title' => '']);
        $this->seeStatusCode(422);
        $this->assertArrayHasKey('title', $this->errors());
    }

    public function test_the_list_has_hosted_and_invited_nights_soonest_first(): void
    {
        $later = $this->night(self::HOST, '2026-11-20T19:00:00Z');
        $sooner = $this->night(self::MARIA, '2026-11-05T19:00:00Z');
        $sooner->participants()->create(['user_id' => self::HOST]);
        $this->night(self::TONIO);

        $this->get('/api/movie-nights', $this->auth());

        $this->seeStatusCode(200)->seeJson(['meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2]]);
        $data = $this->response->getData(true)['data'];
        $this->assertSame([$sooner->id, $later->id], array_column($data, 'id'));
        $this->assertSame(['pending', 'accepted'], array_column($data, 'my_status'));
        $this->assertSame([self::MARIA, self::HOST], array_column($data, 'host_id'));
        $this->assertArrayNotHasKey('participants', $data[0]);
    }

    public function test_the_list_is_paginated(): void
    {
        foreach (range(1, 3) as $day) {
            $this->night(self::HOST, "2026-11-1{$day}T19:00:00Z");
        }

        $this->get('/api/movie-nights?per_page=2&page=2', $this->auth());

        $this->seeStatusCode(200)->seeJson(['meta' => ['current_page' => 2, 'last_page' => 2, 'total' => 3]]);
        $this->assertCount(1, $this->response->getData(true)['data']);
    }

    public function test_a_participant_sees_the_night_with_participants_candidates_and_votes(): void
    {
        $night = $this->night(self::HOST);
        Carbon::setTestNow(Carbon::parse('2026-11-03T18:05:00Z'));
        $night->participants()->create(['user_id' => self::MARIA]);
        $inception = $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);
        $night->candidates()->create(['movie_id' => self::ARRIVAL, 'added_by' => self::MARIA]);
        $night->votes()->create(['user_id' => self::HOST, 'candidate_id' => $inception->id]);
        $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $inception->id]);

        $this->get("/api/movie-nights/{$night->id}", $this->auth(self::MARIA));

        $this->seeStatusCode(200);
        $data = $this->response->getData(true)['data'];
        $this->assertSame('pending', $data['my_status']);
        $this->assertSame([
            ['user_id' => self::HOST, 'status' => 'accepted', 'is_host' => true, 'responded_at' => '2026-11-03T18:00:00Z'],
            ['user_id' => self::MARIA, 'status' => 'pending', 'is_host' => false, 'responded_at' => null],
        ], $data['participants']);
        $this->assertSame([
            ['id' => $inception->id, 'movie_id' => self::INCEPTION, 'added_by' => self::HOST, 'votes' => 2],
            ['id' => $data['candidates'][1]['id'], 'movie_id' => self::ARRIVAL, 'added_by' => self::MARIA, 'votes' => 0],
        ], $data['candidates']);
        $this->assertSame(2, $data['votes_cast']);
    }

    public function test_a_user_who_is_not_invited_gets_404(): void
    {
        $night = $this->night(self::HOST);

        $this->get("/api/movie-nights/{$night->id}", $this->auth(self::TONIO));

        $this->seeStatusCode(404)->seeJsonEquals(['message' => 'Movie night not found.']);
    }

    public function test_an_unknown_or_invalid_id_gets_404(): void
    {
        $this->get('/api/movie-nights/0193a7d3-8888-7aaa-8bbb-000000000600', $this->auth())->seeStatusCode(404);
        $this->get('/api/movie-nights/not-a-uuid', $this->auth())->seeStatusCode(404);
    }

    public function test_every_endpoint_needs_a_token(): void
    {
        $night = $this->night(self::HOST);

        $this->json('POST', '/api/movie-nights', ['title' => 'x'])->seeStatusCode(401);
        $this->get('/api/movie-nights')->seeStatusCode(401);
        $this->get("/api/movie-nights/{$night->id}")->seeStatusCode(401);
    }
}
