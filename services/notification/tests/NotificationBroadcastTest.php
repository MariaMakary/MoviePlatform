<?php

namespace Tests;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use MoviePlatform\Common\Notifications\NotificationType;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class NotificationBroadcastTest extends TestCase
{
    use DatabaseTransactions;

    private const FOLLOWED = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const FOLLOWER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const GUEST = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private AMQPChannel $channel;

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The test's own transaction is level 1, so "after commit" means back to level 1 here.
        DB::connection()->setTransactionManager(new class extends DatabaseTransactionsManager
        {
            public function afterCommitCallbacksShouldBeExecuted($level)
            {
                return $level <= 1;
            }
        });

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
            'occurred_at' => '2026-11-18T10:00:00Z',
            'data' => $data,
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function follow(?string $id = null): void
    {
        $this->deliver('UserFollowed', ['follower_id' => self::FOLLOWER, 'follower_name' => 'Maria Makary', 'following_id' => self::FOLLOWED], $id);
    }

    public function test_a_new_notification_is_broadcast_to_its_user_once_saved(): void
    {
        Event::fake([NotificationCreated::class]);

        $this->follow();

        $notification = Notification::ownedBy(self::FOLLOWED)->sole();
        Event::assertDispatchedTimes(NotificationCreated::class, 1);
        Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($notification) {
            return $event->broadcastOn()->name === 'private-user.'.self::FOLLOWED
                && $event->broadcastAs() === 'NotificationCreated'
                && $event->broadcastWith() === [
                    'id' => $notification->id,
                    'type' => 'new_follower',
                    'title' => 'Maria Makary started following you',
                    'created_at' => $notification->created_at->toIso8601ZuluString(),
                ];
        });
    }

    public function test_the_same_event_twice_is_broadcast_once(): void
    {
        Event::fake([NotificationCreated::class]);
        $id = (string) Str::uuid();

        $this->follow($id);
        $this->follow($id);

        Event::assertDispatchedTimes(NotificationCreated::class, 1);
    }

    public function test_each_participant_of_a_finalized_night_gets_its_own_broadcast(): void
    {
        Event::fake([NotificationCreated::class]);

        $this->deliver('MovieNightFinalized', [
            'movie_night_id' => '0193a7d3-8888-7aaa-8bbb-000000000600',
            'host_id' => self::FOLLOWED,
            'participant_ids' => [self::FOLLOWED, self::FOLLOWER, self::GUEST],
            'winner_movie_id' => '0193a7c1-5555-7aaa-8bbb-000000000001',
            'winner_movie_title' => 'Arrival',
            'scheduled_at' => '2026-11-20T18:00:00Z',
        ]);

        $channels = Event::dispatched(NotificationCreated::class)
            ->map(fn (array $args) => $args[0]->broadcastOn()->name)
            ->sort()->values()->all();
        $this->assertSame(['private-user.'.self::FOLLOWER, 'private-user.'.self::GUEST], $channels);
    }

    public function test_nothing_is_broadcast_when_the_transaction_rolls_back(): void
    {
        Event::fake([NotificationCreated::class]);

        try {
            DB::transaction(function () {
                app(NotificationService::class)->notify(self::FOLLOWED, NotificationType::Welcome, 'Welcome', 'Hello');

                throw new RuntimeException('The handler failed after saving');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Notification::ownedBy(self::FOLLOWED)->count());
        Event::assertNotDispatched(NotificationCreated::class);
    }

    public function test_reverb_down_still_saves_the_notification_and_acks_the_event(): void
    {
        $this->app->make(BroadcastFactory::class)->extend('unreachable', fn () => new class extends Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw new RuntimeException('Connection refused');
            }
        });
        config(['broadcasting.default' => 'unreachable', 'broadcasting.connections.unreachable' => ['driver' => 'unreachable']]);

        $this->follow();

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame(1, Notification::ownedBy(self::FOLLOWED)->count());
    }
}
