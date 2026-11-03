<?php

namespace Tests;

use App\Enums\NotificationType;
use App\Integration\Handlers\UserRegisteredHandler;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class WelcomeNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private const USER_ID = '0193a7c0-1111-7aaa-8bbb-000000000001';

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
            'event' => 'UserRegistered',
            'id' => $id,
            'occurred_at' => '2026-11-01T10:00:00Z',
            'data' => $data + ['user_id' => self::USER_ID, 'name' => 'Joe Rohban', 'email' => 'joe@example.com'],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    public function test_the_handler_is_registered_for_user_registered(): void
    {
        $this->assertSame(UserRegisteredHandler::class, config('events.handlers.UserRegistered'));
    }

    public function test_registering_creates_one_welcome_notification(): void
    {
        $this->deliver((string) Str::uuid());

        $notification = Notification::ownedBy(self::USER_ID)->sole();
        $this->assertSame(NotificationType::Welcome, $notification->type);
        $this->assertSame('Welcome to MoviePlatform!', $notification->title);
        $this->assertStringContainsString('Joe Rohban', $notification->body);
        $this->assertNull($notification->data);
        $this->assertNull($notification->read_at);
    }

    public function test_the_same_event_twice_creates_only_one_notification(): void
    {
        $id = (string) Str::uuid();

        $this->deliver($id);
        $this->deliver($id);

        $this->assertSame(1, Notification::ownedBy(self::USER_ID)->count());
    }

    public function test_unknown_fields_are_ignored(): void
    {
        $this->deliver((string) Str::uuid(), ['avatar' => 'https://example.com/a.png']);

        $this->assertSame(1, Notification::ownedBy(self::USER_ID)->count());
    }

    public function test_the_welcome_notification_counts_as_unread(): void
    {
        $this->deliver((string) Str::uuid());

        $this->assertSame(1, app(NotificationService::class)->unreadCount(self::USER_ID));
    }
}
