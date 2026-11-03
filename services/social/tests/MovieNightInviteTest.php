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

class MovieNightInviteTest extends TestCase
{
    use DatabaseTransactions;

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MARIA = '0193a7c0-2222-7aaa-8bbb-000000000002';

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

        $this->night = MovieNight::factory()->create(['host_id' => self::HOST, 'scheduled_at' => Carbon::parse('2026-11-07T19:00:00Z')]);
        $this->night->participants()->create(['user_id' => self::HOST, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId = self::HOST, string $name = 'Maria Makary'): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => $name, 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function invite(mixed $userIds, string $as = self::HOST): void
    {
        $this->json('POST', "/api/movie-nights/{$this->night->id}/invites", ['user_ids' => $userIds], $this->auth($as));
    }

    private function participants(): array
    {
        return Participant::where('movie_night_id', $this->night->id)->orderBy('user_id')->get()
            ->mapWithKeys(fn (Participant $p) => [$p->user_id => $p->status->value])->all();
    }

    public function test_the_host_invites_users_as_pending_participants(): void
    {
        $this->invite([self::MARIA, self::TONIO]);

        $this->seeStatusCode(200);
        $this->assertSame([self::HOST => 'accepted', self::MARIA => 'pending', self::TONIO => 'pending'], $this->participants());

        $data = $this->response->getData(true)['data'];
        $this->assertSame($this->night->id, $data['id']);
        $this->assertSame(
            [[self::HOST, 'accepted', true], [self::MARIA, 'pending', false], [self::TONIO, 'pending', false]],
            array_map(fn (array $p) => [$p['user_id'], $p['status'], $p['is_host']], $data['participants']),
        );
    }

    public function test_one_movie_night_invite_sent_is_published_per_new_invite(): void
    {
        $this->invite([self::MARIA, self::TONIO]);

        $this->assertCount(2, $this->published);
        foreach ([self::MARIA, self::TONIO] as $i => $userId) {
            [$routingKey, $envelope] = $this->published[$i];
            $this->assertSame('movie_night.invite_sent', $routingKey);
            $this->assertSame('MovieNightInviteSent', $envelope['event']);
            $this->assertSame([
                'movie_night_id' => $this->night->id,
                'host_id' => self::HOST,
                'host_name' => 'Maria Makary',
                'user_id' => $userId,
                'scheduled_at' => '2026-11-07T19:00:00Z',
            ], $envelope['data']);
        }
    }

    public function test_inviting_twice_creates_no_duplicates_and_no_second_event(): void
    {
        $this->invite([self::MARIA]);
        $this->published = [];

        $this->invite([self::MARIA, self::TONIO]);

        $this->seeStatusCode(200);
        $this->assertSame([self::HOST => 'accepted', self::MARIA => 'pending', self::TONIO => 'pending'], $this->participants());
        $this->assertSame([self::TONIO], array_map(fn (array $p) => $p[1]['data']['user_id'], $this->published));
    }

    public function test_the_host_and_users_who_already_answered_are_skipped(): void
    {
        $this->night->participants()->create(['user_id' => self::MARIA, 'status' => ParticipantStatus::Declined, 'responded_at' => Carbon::now()]);

        $this->invite([self::HOST, strtoupper(self::MARIA)]);

        $this->seeStatusCode(200);
        $this->assertSame([self::HOST => 'accepted', self::MARIA => 'declined'], $this->participants());
        $this->assertSame([], $this->published);
    }

    public function test_a_participant_who_is_not_the_host_gets_403(): void
    {
        $this->night->participants()->create(['user_id' => self::MARIA]);

        $this->invite([self::TONIO], self::MARIA);

        $this->seeStatusCode(403)->seeJsonEquals(['message' => 'Only the host can invite people.']);
        $this->assertArrayNotHasKey(self::TONIO, $this->participants());
        $this->assertSame([], $this->published);
    }

    public function test_a_user_who_cannot_see_the_night_gets_404(): void
    {
        $this->invite([self::TONIO], self::JOY);

        $this->seeStatusCode(404)->seeJsonEquals(['message' => 'Movie night not found.']);
        $this->assertSame([], $this->published);
    }

    public function test_a_night_that_is_no_longer_open_gets_422(): void
    {
        foreach ([MovieNightStatus::Finalized, MovieNightStatus::Cancelled] as $status) {
            $this->night->status = $status;
            $this->night->save();

            $this->invite([self::MARIA]);

            $this->seeStatusCode(422)->seeJsonEquals(['message' => 'This movie night is no longer open.']);
        }

        $this->assertSame([self::HOST => 'accepted'], $this->participants());
        $this->assertSame([], $this->published);
    }

    public function test_the_user_ids_are_validated(): void
    {
        $tooMany = array_map(fn (int $i) => sprintf('0193a7c0-9999-7aaa-8bbb-%012d', $i), range(1, 21));
        $cases = [
            'missing' => [null, 'user_ids'],
            'empty' => [[], 'user_ids'],
            'not an array' => [self::MARIA, 'user_ids'],
            'not a uuid' => [['maria'], 'user_ids.0'],
            'repeated' => [[self::MARIA, strtoupper(self::MARIA)], 'user_ids.0'],
            'more than 20' => [$tooMany, 'user_ids'],
        ];

        foreach ($cases as $case => [$userIds, $field]) {
            $body = $userIds === null ? [] : ['user_ids' => $userIds];
            $this->json('POST', "/api/movie-nights/{$this->night->id}/invites", $body, $this->auth());

            $this->assertSame(422, $this->response->getStatusCode(), $case);
            $this->assertArrayHasKey($field, $this->response->getData(true)['errors'], $case);
        }

        $this->assertSame([self::HOST => 'accepted'], $this->participants());
        $this->assertSame([], $this->published);
    }

    public function test_twenty_users_can_be_invited_at_once(): void
    {
        $this->invite(array_map(fn (int $i) => sprintf('0193a7c0-9999-7aaa-8bbb-%012d', $i), range(1, 20)));

        $this->seeStatusCode(200);
        $this->assertCount(21, $this->participants());
        $this->assertCount(20, $this->published);
    }

    public function test_the_invited_user_sees_the_night_in_their_list(): void
    {
        $this->invite([self::MARIA]);

        $this->get('/api/movie-nights', $this->auth(self::MARIA));

        $this->seeStatusCode(200);
        $data = $this->response->getData(true)['data'];
        $this->assertSame([$this->night->id], array_column($data, 'id'));
        $this->assertSame('pending', $data[0]['my_status']);
    }

    public function test_a_guest_gets_401_and_an_invalid_id_gets_404(): void
    {
        $this->json('POST', "/api/movie-nights/{$this->night->id}/invites", ['user_ids' => [self::MARIA]])->seeStatusCode(401);
        $this->json('POST', '/api/movie-nights/not-a-uuid/invites', ['user_ids' => [self::MARIA]], $this->auth())->seeStatusCode(404);
    }
}
