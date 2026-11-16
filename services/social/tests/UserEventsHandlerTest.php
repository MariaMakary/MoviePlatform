<?php

namespace Tests;

use App\Integration\Handlers\UserBlockedHandler;
use App\Integration\Handlers\UserDeletedHandler;
use App\Integration\Handlers\UserUnblockedHandler;
use App\Models\Activity;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class UserEventsHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const BOB = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const ANA = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private AMQPChannel $channel;

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);

        $this->channel = $this->createMock(AMQPChannel::class);
        $this->channel->method('basic_ack')->willReturnCallback(function () {
            $this->answers[] = 'ack';
        });
        $this->channel->method('basic_reject')->willReturnCallback(function ($tag, bool $requeue) {
            $this->answers[] = $requeue ? 'requeue' : 'reject';
        });
        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
    }

    private function deliver(string $event, string $userId): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $event, 'id' => (string) Str::uuid(), 'occurred_at' => '2026-11-16T10:00:00Z', 'data' => ['user_id' => $userId],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function auth(string $userId = self::ME): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Tonio', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function activity(string $userId): Activity
    {
        return Activity::forceCreate([
            'event_id' => (string) Str::uuid(), 'user_id' => $userId, 'type' => 'rating', 'subject_id' => (string) Str::uuid(),
            'movie_id' => self::MOVIE, 'data' => ['score' => 4], 'created_at' => Carbon::parse('2026-11-16T09:00:00Z'),
        ]);
    }

    private function follow(string $follower, string $following): void
    {
        DB::table('follows')->insert(['follower_id' => $follower, 'following_id' => $following, 'created_at' => Carbon::now()]);
    }

    private function night(string $hostId): string
    {
        $id = (string) Str::uuid();
        DB::table('movie_nights')->insert(['id' => $id, 'host_id' => $hostId, 'title' => 'Friday', 'scheduled_at' => Carbon::parse('2026-11-20T20:00:00Z'),
            'status' => 'open', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
        $this->participant($id, $hostId, 'accepted');

        return $id;
    }

    private function participant(string $nightId, string $userId, string $status = 'pending'): void
    {
        DB::table('participants')->insert(['movie_night_id' => $nightId, 'user_id' => $userId, 'status' => $status, 'created_at' => Carbon::now()]);
    }

    private function candidate(string $nightId, string $addedBy): string
    {
        $id = (string) Str::uuid();
        DB::table('candidates')->insert(['id' => $id, 'movie_night_id' => $nightId, 'movie_id' => (string) Str::uuid(), 'added_by' => $addedBy, 'created_at' => Carbon::now()]);

        return $id;
    }

    private function ids(): array
    {
        return array_column(json_decode($this->response->getContent(), true)['data'], 'id');
    }

    public function test_the_three_handlers_are_bound(): void
    {
        $this->assertSame(UserBlockedHandler::class, config('events.handlers.UserBlocked'));
        $this->assertSame(UserUnblockedHandler::class, config('events.handlers.UserUnblocked'));
        $this->assertSame(UserDeletedHandler::class, config('events.handlers.UserDeleted'));
        foreach (['user.blocked', 'user.unblocked', 'user.deleted'] as $key) {
            $this->assertContains($key, config('events.bindings'));
        }
    }

    public function test_a_blocked_users_activity_disappears_from_the_feed_and_their_profile(): void
    {
        $this->follow(self::ME, self::BOB);
        $this->follow(self::ME, self::ANA);
        $this->activity(self::BOB);
        $anas = $this->activity(self::ANA);

        $this->deliver('UserBlocked', self::BOB);

        $this->get('/api/feed', $this->auth())->seeStatusCode(200);
        $this->assertSame([$anas->id], $this->ids());
        $this->get('/api/users/'.self::BOB.'/activity')->seeStatusCode(200);
        $this->assertSame([], $this->ids());
        $this->assertSame(['ack'], $this->answers);
    }

    public function test_unblocking_shows_the_activity_again(): void
    {
        $this->follow(self::ME, self::BOB);
        $bobs = $this->activity(self::BOB);

        $this->deliver('UserBlocked', self::BOB);
        $this->deliver('UserUnblocked', self::BOB);

        $this->get('/api/feed', $this->auth())->seeStatusCode(200);
        $this->assertSame([$bobs->id], $this->ids());
        $this->assertFalse($bobs->fresh()->author_blocked);
    }

    public function test_blocking_one_user_leaves_the_others_untouched(): void
    {
        $this->activity(self::BOB);
        $anas = $this->activity(self::ANA);

        $this->deliver('UserBlocked', self::BOB);

        $this->assertFalse($anas->fresh()->author_blocked);
    }

    public function test_a_deleted_user_loses_everything_social_keeps_about_them(): void
    {
        // Bob's own things
        $this->follow(self::ME, self::BOB);
        $this->follow(self::BOB, self::ANA);
        DB::table('privacy_settings')->insert(['user_id' => self::BOB, 'show_reviews' => false, 'show_ratings' => true, 'show_lists' => true, 'show_history' => true]);
        $this->activity(self::BOB);
        $bobsNight = $this->night(self::BOB);
        $this->participant($bobsNight, self::ME, 'accepted');

        // Bob on my night: invited, proposed a movie and voted for it
        $myNight = $this->night(self::ME);
        $this->participant($myNight, self::BOB, 'accepted');
        $bobsPick = $this->candidate($myNight, self::BOB);
        DB::table('votes')->insert(['movie_night_id' => $myNight, 'user_id' => self::BOB, 'candidate_id' => $bobsPick]);
        DB::table('votes')->insert(['movie_night_id' => $myNight, 'user_id' => self::ME, 'candidate_id' => $bobsPick]);

        // Chat
        $room = (string) Str::uuid();
        DB::table('rooms')->insert(['id' => $room, 'movie_id' => self::MOVIE, 'name' => 'Inception', 'created_at' => Carbon::now()]);
        foreach ([self::BOB, self::ME] as $author) {
            DB::table('messages')->insert(['id' => (string) Str::uuid(), 'room_id' => $room, 'user_id' => $author, 'body' => 'Hi', 'created_at' => Carbon::now()]);
        }

        // What must stay
        $this->follow(self::ME, self::ANA);
        $anas = $this->activity(self::ANA);

        $this->deliver('UserDeleted', self::BOB);

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame(0, DB::table('follows')->where('follower_id', self::BOB)->orWhere('following_id', self::BOB)->count());
        $this->assertSame(1, DB::table('follows')->count() - DB::table('follows')->where('follower_id', '<>', self::ME)->count(), 'My follow of Ana stays.');
        $this->assertNull(DB::table('privacy_settings')->where('user_id', self::BOB)->first());
        $this->assertSame([$anas->id], Activity::whereIn('user_id', [self::BOB, self::ANA])->pluck('id')->all());
        $this->assertNull(DB::table('movie_nights')->where('id', $bobsNight)->first(), 'The night Bob hosted is gone, with its participants.');
        $this->assertSame(0, DB::table('participants')->where('movie_night_id', $bobsNight)->count());
        $this->assertSame([self::ME], DB::table('participants')->where('movie_night_id', $myNight)->pluck('user_id')->all());
        $this->assertSame([self::ME], DB::table('votes')->where('movie_night_id', $myNight)->pluck('user_id')->all());
        $this->assertNotNull(DB::table('candidates')->where('id', $bobsPick)->first(), 'His pick stays: I voted for it.');
        $this->assertSame([self::ME], DB::table('messages')->where('room_id', $room)->pluck('user_id')->all());
    }
}
