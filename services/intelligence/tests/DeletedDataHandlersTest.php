<?php

namespace Tests;

use App\Integration\Handlers\MovieDeletedHandler;
use App\Integration\Handlers\UserDeletedHandler;
use App\Models\Recommendation;
use App\Models\UserTasteProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class DeletedDataHandlersTest extends TestCase
{
    use DatabaseTransactions;

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const JOY = '0193a7c0-4444-7aaa-8bbb-000000000004';

    private const SCI_FI = '0193a7b0-2222-7aaa-8bbb-000000000010';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private AMQPChannel $channel;

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->channel = $this->createMock(AMQPChannel::class);
        $this->channel->method('basic_ack')->willReturnCallback(function () {
            $this->answers[] = 'ack';
        });
        $this->channel->method('basic_reject')->willReturnCallback(function ($deliveryTag, bool $requeue) {
            $this->answers[] = $requeue ? 'requeue' : 'reject';
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
    }

    private function deliver(string $event, array $data, ?string $id = null): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $event,
            'id' => $id ?? (string) Str::uuid(),
            'occurred_at' => '2026-11-15T10:00:00Z',
            'data' => $data,
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function profile(string $userId, array $seen): UserTasteProfile
    {
        return UserTasteProfile::create([
            'user_id' => $userId,
            'genre_weights' => [self::SCI_FI => 4],
            'seen_movie_ids' => $seen,
        ]);
    }

    private function recommend(string $userId, string $movieId): void
    {
        Recommendation::forceCreate(['user_id' => $userId, 'movie_id' => $movieId, 'score' => 0.8, 'reason' => 'Because you like Science Fiction']);
    }

    public function test_the_handlers_are_bound(): void
    {
        $this->assertSame(MovieDeletedHandler::class, config('events.handlers.MovieDeleted'));
        $this->assertSame(UserDeletedHandler::class, config('events.handlers.UserDeleted'));
        $this->assertContains('movie.deleted', config('events.bindings'));
        $this->assertContains('user.deleted', config('events.bindings'));
    }

    public function test_a_deleted_movie_leaves_recommendations_and_seen_movies(): void
    {
        $tonio = $this->profile(self::TONIO, [self::INCEPTION => 5, self::ARRIVAL => null]);
        $joy = $this->profile(self::JOY, [self::ARRIVAL => 4]);
        $this->recommend(self::TONIO, self::INCEPTION);
        $this->recommend(self::JOY, self::INCEPTION);
        $this->recommend(self::JOY, self::ARRIVAL);

        $this->deliver('MovieDeleted', ['movie_id' => self::INCEPTION]);

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame([self::ARRIVAL => null], $tonio->fresh()->seen_movie_ids);
        $this->assertSame([self::ARRIVAL => 4], $joy->fresh()->seen_movie_ids);
        $this->assertSame([self::SCI_FI => 4], $tonio->fresh()->genre_weights);
        $this->assertSame(0, Recommendation::where('movie_id', self::INCEPTION)->count());
        $this->assertSame([self::ARRIVAL], Recommendation::forUser(self::JOY)->pluck('movie_id')->all());
        Http::assertNothingSent();
    }

    public function test_a_deleted_user_has_no_profile_or_recommendations_left(): void
    {
        $this->profile(self::TONIO, [self::INCEPTION => 5]);
        $this->profile(self::JOY, [self::INCEPTION => 4]);
        $this->recommend(self::TONIO, self::ARRIVAL);
        $this->recommend(self::JOY, self::ARRIVAL);

        $this->deliver('UserDeleted', ['user_id' => self::TONIO]);

        $this->assertSame(['ack'], $this->answers);
        $this->assertFalse(UserTasteProfile::where('user_id', self::TONIO)->exists());
        $this->assertSame(0, Recommendation::forUser(self::TONIO)->count());
        $this->assertTrue(UserTasteProfile::where('user_id', self::JOY)->exists());
        $this->assertSame(1, Recommendation::forUser(self::JOY)->count());
    }

    public function test_the_same_event_twice_or_unknown_ids_are_acknowledged(): void
    {
        $this->profile(self::TONIO, [self::INCEPTION => 5]);
        $movieEvent = (string) Str::uuid();
        $userEvent = (string) Str::uuid();

        $this->deliver('MovieDeleted', ['movie_id' => self::INCEPTION], $movieEvent);
        $this->deliver('MovieDeleted', ['movie_id' => self::INCEPTION], $movieEvent);
        $this->deliver('MovieDeleted', ['movie_id' => self::ARRIVAL]);
        $this->deliver('UserDeleted', ['user_id' => self::TONIO], $userEvent);
        $this->deliver('UserDeleted', ['user_id' => self::TONIO], $userEvent);
        $this->deliver('UserDeleted', ['user_id' => self::JOY]);

        $this->assertSame(['ack', 'ack', 'ack', 'ack', 'ack', 'ack'], $this->answers);
    }
}
