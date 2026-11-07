<?php

namespace Tests;

use App\Integration\Handlers\UserFollowedHandler;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use MoviePlatform\Common\Notifications\NotificationType;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class NewFollowerNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private const FOLLOWED = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const FOLLOWER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private AMQPChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = $this->createMock(AMQPChannel::class);

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
    }

    private function deliver(string $id, array $data = []): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => 'UserFollowed',
            'id' => $id,
            'occurred_at' => '2026-11-07T10:00:00Z',
            'data' => $data + ['follower_id' => self::FOLLOWER, 'follower_name' => 'Maria Makary', 'following_id' => self::FOLLOWED],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    public function test_the_handler_is_registered_for_user_followed(): void
    {
        $this->assertSame(UserFollowedHandler::class, config('events.handlers.UserFollowed'));
        $this->assertContains('user.followed', config('events.bindings'));
    }

    public function test_following_creates_one_notification_for_the_followed_user(): void
    {
        $this->deliver((string) Str::uuid());

        $notification = Notification::ownedBy(self::FOLLOWED)->sole();
        $this->assertSame(NotificationType::NewFollower, $notification->type);
        $this->assertSame('Maria Makary started following you', $notification->title);
        $this->assertNotSame('', $notification->body);
        $this->assertSame(['follower_id' => self::FOLLOWER], $notification->data);
        $this->assertNull($notification->read_at);

        $this->assertSame(0, Notification::ownedBy(self::FOLLOWER)->count(), 'The follower gets nothing.');
        $this->assertSame(1, app(NotificationService::class)->unreadCount(self::FOLLOWED));
    }

    public function test_the_same_event_twice_creates_only_one_notification(): void
    {
        $id = (string) Str::uuid();

        $this->deliver($id);
        $this->deliver($id);

        $this->assertSame(1, Notification::ownedBy(self::FOLLOWED)->count());
    }

    public function test_two_follows_create_two_notifications(): void
    {
        $this->deliver((string) Str::uuid());
        $this->deliver((string) Str::uuid(), ['follower_id' => '0193a7c0-3333-7aaa-8bbb-000000000003', 'follower_name' => 'Tonio']);

        $this->assertSame(2, Notification::ownedBy(self::FOLLOWED)->count());
    }

    public function test_the_type_is_stored_as_new_follower(): void
    {
        $this->deliver((string) Str::uuid());

        $notification = Notification::ownedBy(self::FOLLOWED)->sole();
        $this->assertSame('new_follower', $notification->getRawOriginal('type'));
    }
}
