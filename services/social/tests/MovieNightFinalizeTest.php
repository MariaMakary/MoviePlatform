<?php

namespace Tests;

use App\Events\VotesUpdated;
use App\Models\Candidate;
use App\Models\MovieNight;
use App\Models\Vote;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class MovieNightFinalizeTest extends TestCase
{
    use DatabaseTransactions;

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const JOE = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const JOY = '0193a7c0-4444-7aaa-8bbb-000000000004';

    private const MARK = '0193a7c0-6666-7aaa-8bbb-000000000006';

    private const STRANGER = '0193a7c0-5555-7aaa-8bbb-000000000005';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private array $published = [];

    private array $titles = [self::INCEPTION => 'Inception', self::ARRIVAL => 'Arrival'];

    private bool $catalogDown = false;

    private MovieNight $night;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem'),
            'internal.services.catalog' => 'http://catalog.test',
            'internal.service_token' => 'test-service-token',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-15T18:00:00Z'));
        Event::fake([VotesUpdated::class]);

        Http::fake(function (Request $request) {
            if ($this->catalogDown) {
                return Http::response(['message' => 'Server error.'], 500);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $id = $query['ids'] ?? '';

            return Http::response(['data' => isset($this->titles[$id]) ? [['id' => $id, 'title' => $this->titles[$id]]] : []]);
        });

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true)];
        });
        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        $this->night = MovieNight::factory()->create([
            'host_id' => self::HOST,
            'scheduled_at' => '2026-11-20T19:00:00Z',
            'location' => null,
            'meeting_link' => 'https://meet.example.com/abc',
        ]);
        $this->night->participants()->create(['user_id' => self::HOST, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
        $this->night->participants()->create(['user_id' => self::JOE, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
        $this->night->participants()->create(['user_id' => self::TONIO]);
        $this->night->participants()->create(['user_id' => self::JOY, 'status' => ParticipantStatus::Declined, 'responded_at' => Carbon::now()]);
        $this->night->participants()->create(['user_id' => self::MARK, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Someone', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function finalize(array $body = [], string $as = self::HOST): void
    {
        $this->json('POST', "/api/movie-nights/{$this->night->id}/finalize", $body, $this->auth($as));
    }

    private function cancel(string $as = self::HOST): void
    {
        $this->json('POST', "/api/movie-nights/{$this->night->id}/cancel", [], $this->auth($as));
    }

    private function candidate(string $movieId, array $voters = []): Candidate
    {
        $candidate = $this->night->candidates()->create(['movie_id' => $movieId, 'added_by' => self::JOE]);

        foreach ($voters as $userId) {
            Vote::insert([
                'movie_night_id' => $this->night->id,
                'user_id' => $userId,
                'candidate_id' => $candidate->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return $candidate;
    }

    private function assertStillOpen(): void
    {
        $night = $this->night->fresh();
        $this->assertSame(MovieNightStatus::Open, $night->status);
        $this->assertNull($night->winner_movie_id);
        $this->assertSame([], $this->published);
    }

    public function test_the_host_finalizes_and_the_movie_with_most_votes_wins(): void
    {
        $this->candidate(self::INCEPTION, [self::HOST, self::JOE]);
        $this->candidate(self::ARRIVAL, [self::MARK]);

        $this->finalize();

        $this->seeStatusCode(200);
        $this->seeJson(['status' => 'finalized', 'winner_movie_id' => self::INCEPTION]);
        $night = $this->night->fresh();
        $this->assertSame(MovieNightStatus::Finalized, $night->status);
        $this->assertSame(self::INCEPTION, $night->winner_movie_id);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://catalog.test/api/internal/movies')
            && $request['ids'] === self::INCEPTION
            && $request->hasHeader('X-Service-Token', 'test-service-token'));
    }

    public function test_the_finalized_event_has_every_field(): void
    {
        $this->candidate(self::INCEPTION, [self::JOE]);

        $this->finalize();

        $this->assertSame([['movie_night.finalized', [
            'movie_night_id' => $this->night->id,
            'host_id' => self::HOST,
            'participant_ids' => [self::JOE, self::MARK],
            'winner_movie_id' => self::INCEPTION,
            'winner_movie_title' => 'Inception',
            'scheduled_at' => '2026-11-20T19:00:00Z',
            'location' => null,
            'meeting_link' => 'https://meet.example.com/abc',
        ]]], array_map(fn (array $message) => [$message[0], $message[1]['data']], $this->published));
    }

    public function test_a_tie_needs_the_host_to_choose_one_of_the_tied_movies(): void
    {
        $this->candidate(self::INCEPTION, [self::JOE]);
        $arrival = $this->candidate(self::ARRIVAL, [self::MARK]);

        $this->finalize();

        $this->seeStatusCode(422);
        $this->seeJson(['errors' => ['candidate_id' => ['It is a tie: choose one of the movies with the most votes.']]]);
        $this->assertStillOpen();

        $this->finalize(['candidate_id' => strtoupper($arrival->id)]);

        $this->seeStatusCode(200);
        $this->assertSame(self::ARRIVAL, $this->night->fresh()->winner_movie_id);
    }

    public function test_a_shortlist_without_votes_is_a_tie_too(): void
    {
        $inception = $this->candidate(self::INCEPTION);
        $this->candidate(self::ARRIVAL);

        $this->finalize();
        $this->seeStatusCode(422);

        $this->finalize(['candidate_id' => $inception->id]);
        $this->seeStatusCode(200);
        $this->assertSame(self::INCEPTION, $this->night->fresh()->winner_movie_id);
    }

    public function test_the_chosen_movie_must_be_one_with_the_most_votes(): void
    {
        $this->candidate(self::INCEPTION, [self::JOE, self::MARK]);
        $arrival = $this->candidate(self::ARRIVAL, [self::HOST]);

        foreach ([$arrival->id, '0193a7d9-9999-7aaa-8bbb-000000000999'] as $candidateId) {
            $this->finalize(['candidate_id' => $candidateId]);

            $this->seeStatusCode(422);
            $this->seeJson(['errors' => ['candidate_id' => ['Choose one of the movies with the most votes.']]]);
        }

        $this->assertStillOpen();
    }

    public function test_an_empty_shortlist_cannot_be_finalized(): void
    {
        $this->finalize();

        $this->seeStatusCode(422);
        $this->seeJson(['message' => 'Add a movie to the shortlist first.']);
        $this->assertStillOpen();
    }

    public function test_a_night_is_finalized_only_once(): void
    {
        $this->candidate(self::INCEPTION, [self::JOE]);
        $this->finalize();
        $this->seeStatusCode(200);

        $this->finalize();

        $this->seeStatusCode(422);
        $this->seeJson(['message' => 'This movie night is no longer open.']);
        $this->assertCount(1, $this->published);
    }

    public function test_only_the_host_finalizes(): void
    {
        $this->candidate(self::INCEPTION, [self::JOE]);

        $this->finalize([], self::JOE);
        $this->seeStatusCode(403);
        $this->seeJson(['message' => 'Only the host can finalize the movie night.']);

        $this->finalize([], self::STRANGER);
        $this->seeStatusCode(404);
        $this->seeJson(['message' => 'Movie night not found.']);

        $this->assertStillOpen();
    }

    public function test_catalog_down_gives_503_and_changes_nothing(): void
    {
        $this->candidate(self::INCEPTION, [self::JOE]);
        $this->catalogDown = true;

        $this->finalize();

        $this->seeStatusCode(503);
        $this->seeJson(['message' => 'Catalog is unavailable.']);
        $this->assertStillOpen();
    }

    public function test_a_winner_no_longer_in_the_catalog_gives_422(): void
    {
        $this->titles = [];
        $this->candidate(self::INCEPTION, [self::JOE]);

        $this->finalize();

        $this->seeStatusCode(422);
        $this->seeJson(['message' => 'The winning movie is no longer in the catalog.']);
        $this->assertStillOpen();
    }

    public function test_the_candidate_id_is_validated(): void
    {
        $this->candidate(self::INCEPTION, [self::JOE]);

        $this->finalize(['candidate_id' => 'not-a-uuid']);

        $this->seeStatusCode(422);
        $this->seeJsonStructure(['errors' => ['candidate_id']]);
        $this->assertStillOpen();
    }

    public function test_the_host_cancels_an_open_night_without_an_event(): void
    {
        $this->cancel();

        $this->seeStatusCode(200);
        $this->seeJson(['status' => 'cancelled', 'winner_movie_id' => null]);
        $this->assertSame(MovieNightStatus::Cancelled, $this->night->fresh()->status);
        $this->assertSame([], $this->published);
    }

    public function test_a_cancelled_night_refuses_every_change(): void
    {
        $inception = $this->candidate(self::INCEPTION, [self::JOE]);
        $this->cancel();
        $id = $this->night->id;

        $requests = [
            ['POST', "/api/movie-nights/{$id}/finalize", [], self::HOST],
            ['POST', "/api/movie-nights/{$id}/cancel", [], self::HOST],
            ['POST', "/api/movie-nights/{$id}/votes", ['candidate_id' => $inception->id], self::MARK],
            ['POST', "/api/movie-nights/{$id}/candidates", ['movie_id' => self::ARRIVAL], self::JOE],
            ['DELETE', "/api/movie-nights/{$id}/candidates/{$inception->id}", [], self::JOE],
            ['POST', "/api/movie-nights/{$id}/invites", ['user_ids' => [self::STRANGER]], self::HOST],
            ['PATCH', "/api/movie-nights/{$id}/rsvp", ['status' => 'accepted'], self::TONIO],
        ];

        foreach ($requests as [$method, $uri, $body, $as]) {
            $this->json($method, $uri, $body, $this->auth($as));

            $this->seeStatusCode(422);
            $this->seeJson(['message' => 'This movie night is no longer open.']);
        }

        $this->assertSame(MovieNightStatus::Cancelled, $this->night->fresh()->status);
        $this->assertSame([], $this->published);
        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_only_the_host_cancels_and_a_finalized_night_cannot_be_cancelled(): void
    {
        $this->cancel(self::JOE);
        $this->seeStatusCode(403);
        $this->seeJson(['message' => 'Only the host can cancel the movie night.']);

        $this->cancel(self::STRANGER);
        $this->seeStatusCode(404);

        $this->candidate(self::INCEPTION, [self::JOE]);
        $this->finalize();
        $this->cancel();
        $this->seeStatusCode(422);
        $this->assertSame(MovieNightStatus::Finalized, $this->night->fresh()->status);
    }
}
