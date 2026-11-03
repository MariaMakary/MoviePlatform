<?php

namespace Tests;

use App\Enums\MovieNightStatus;
use App\Enums\ParticipantStatus;
use App\Models\MovieNight;
use App\Models\Participant;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class MovieNightRsvpTest extends TestCase
{
    use DatabaseTransactions;

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const JOY = '0193a7c0-4444-7aaa-8bbb-000000000004';

    private array $published = [];

    private MovieNight $night;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
        Carbon::setTestNow(Carbon::parse('2026-11-03T18:00:00Z'));

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true)];
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        $this->night = MovieNight::factory()->create(['host_id' => self::HOST]);
        $this->night->participants()->create(['user_id' => self::HOST, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
        $this->night->participants()->create(['user_id' => self::TONIO]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId = self::TONIO, string $name = 'Tonio Jreige'): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => $name, 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function rsvp(mixed $status, string $as = self::TONIO): void
    {
        $this->json('PATCH', "/api/movie-nights/{$this->night->id}/rsvp", ['status' => $status], $this->auth($as));
    }

    private function tonio(): Participant
    {
        return Participant::where('movie_night_id', $this->night->id)->where('user_id', self::TONIO)->firstOrFail();
    }

    public function test_an_invited_user_accepts(): void
    {
        $this->rsvp('accepted');

        $this->seeStatusCode(200);
        $this->assertSame(ParticipantStatus::Accepted, $this->tonio()->status);
        $this->assertSame('2026-11-03T18:00:00Z', $this->tonio()->responded_at->toIso8601ZuluString());

        $data = $this->response->getData(true)['data'];
        $this->assertSame('accepted', $data['my_status']);
        $this->assertSame(['user_id' => self::TONIO, 'status' => 'accepted', 'is_host' => false, 'responded_at' => '2026-11-03T18:00:00Z'], $data['participants'][1]);
    }

    public function test_an_invited_user_declines(): void
    {
        $this->rsvp('declined');

        $this->seeStatusCode(200)->seeJson(['my_status' => 'declined']);
        $this->assertSame(ParticipantStatus::Declined, $this->tonio()->status);
    }

    public function test_the_answer_can_be_changed_while_the_night_is_open(): void
    {
        $this->rsvp('declined');
        Carbon::setTestNow(Carbon::parse('2026-11-04T09:30:00Z'));

        $this->rsvp('accepted');

        $this->seeStatusCode(200);
        $this->assertSame(ParticipantStatus::Accepted, $this->tonio()->status);
        $this->assertSame('2026-11-04T09:30:00Z', $this->tonio()->responded_at->toIso8601ZuluString());
        $this->assertSame(['declined', 'accepted'], array_map(fn (array $p) => $p[1]['data']['status'], $this->published));
    }

    public function test_movie_night_invite_answered_is_published_with_user_name(): void
    {
        $this->rsvp('accepted');

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope] = $this->published[0];
        $this->assertSame('movie_night.invite_answered', $routingKey);
        $this->assertSame('MovieNightInviteAnswered', $envelope['event']);
        $this->assertSame([
            'movie_night_id' => $this->night->id,
            'host_id' => self::HOST,
            'user_id' => self::TONIO,
            'user_name' => 'Tonio Jreige',
            'status' => 'accepted',
        ], $envelope['data']);
    }

    public function test_sending_the_same_answer_again_changes_nothing_and_tells_the_host_once(): void
    {
        $this->rsvp('accepted');
        Carbon::setTestNow(Carbon::parse('2026-11-04T09:30:00Z'));

        $this->rsvp('accepted');

        $this->seeStatusCode(200);
        $this->assertSame('2026-11-03T18:00:00Z', $this->tonio()->responded_at->toIso8601ZuluString());
        $this->assertCount(1, $this->published);
    }

    public function test_the_host_gets_422(): void
    {
        $this->rsvp('declined', self::HOST);

        $this->seeStatusCode(422)->seeJsonEquals(['message' => 'The host is always going.']);
        $this->assertSame(
            ParticipantStatus::Accepted,
            Participant::where('movie_night_id', $this->night->id)->where('user_id', self::HOST)->first()->status,
        );
        $this->assertSame([], $this->published);
    }

    public function test_a_user_who_is_not_invited_gets_404(): void
    {
        $this->rsvp('accepted', self::JOY);

        $this->seeStatusCode(404)->seeJsonEquals(['message' => 'Movie night not found.']);
        $this->assertSame(0, Participant::where('user_id', self::JOY)->count());
        $this->assertSame([], $this->published);
    }

    public function test_a_night_that_is_no_longer_open_gets_422(): void
    {
        foreach ([MovieNightStatus::Finalized, MovieNightStatus::Cancelled] as $status) {
            $this->night->status = $status;
            $this->night->save();

            $this->rsvp('accepted');

            $this->seeStatusCode(422)->seeJsonEquals(['message' => 'This movie night is no longer open.']);
        }

        $this->assertSame(ParticipantStatus::Pending, $this->tonio()->status);
        $this->assertSame([], $this->published);
    }

    public function test_the_status_is_validated(): void
    {
        foreach ([null, '', 'pending', 'maybe', 'ACCEPTED', 1] as $status) {
            $this->rsvp($status);

            $this->assertSame(422, $this->response->getStatusCode(), var_export($status, true));
            $this->assertArrayHasKey('status', $this->response->getData(true)['errors']);
        }

        $this->assertSame(ParticipantStatus::Pending, $this->tonio()->status);
        $this->assertSame([], $this->published);
    }

    public function test_a_guest_gets_401_and_an_invalid_id_gets_404(): void
    {
        $this->json('PATCH', "/api/movie-nights/{$this->night->id}/rsvp", ['status' => 'accepted'])->seeStatusCode(401);
        $this->json('PATCH', '/api/movie-nights/not-a-uuid/rsvp', ['status' => 'accepted'], $this->auth())->seeStatusCode(404);
    }
}
