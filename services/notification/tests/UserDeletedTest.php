<?php

namespace Tests;

use App\Integration\Handlers\UserDeletedHandler;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use MoviePlatform\Common\Notifications\NotificationType;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class UserDeletedTest extends TestCase
{
    use DatabaseTransactions;

    private const DELETED = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private AMQPChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = $this->createMock(AMQPChannel::class);

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
    }

    private function deliver(string $id, string $userId = self::DELETED): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => 'UserDeleted',
            'id' => $id,
            'occurred_at' => '2026-11-24T10:00:00Z',
            'data' => ['user_id' => $userId],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function notificationFor(string $userId, ?string $readAt = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => NotificationType::Welcome,
            'title' => 'Welcome',
            'body' => 'Hello.',
            'read_at' => $readAt,
        ]);
    }

    public function test_the_handler_is_registered_for_user_deleted_and_user_blocked_is_not_consumed(): void
    {
        $this->assertSame(UserDeletedHandler::class, config('events.handlers.UserDeleted'));
        $this->assertContains('user.deleted', config('events.bindings'));
        $this->assertNotContains('user.blocked', config('events.bindings'));
        $this->assertArrayNotHasKey('UserBlocked', config('events.handlers'));
    }

    public function test_deleting_a_user_removes_all_their_notifications_and_only_theirs(): void
    {
        $this->notificationFor(self::DELETED);
        $this->notificationFor(self::DELETED, '2026-11-20T10:00:00Z');
        $kept = $this->notificationFor(self::OTHER);

        $this->deliver((string) Str::uuid());

        $this->assertSame(0, Notification::ownedBy(self::DELETED)->count());
        $this->assertSame([$kept->id], Notification::ownedBy(self::OTHER)->pluck('id')->all());
        $this->assertSame(0, app(NotificationService::class)->unreadCount(self::DELETED));
    }

    public function test_the_same_event_twice_is_processed_once(): void
    {
        $this->notificationFor(self::DELETED);
        $id = (string) Str::uuid();

        $this->deliver($id);
        $this->notificationFor(self::DELETED);
        $this->deliver($id);

        $this->assertSame(1, DB::table('processed_events')->where('event_id', $id)->count());
        $this->assertSame(1, Notification::ownedBy(self::DELETED)->count(), 'The duplicate is skipped, so it deletes nothing.');
    }

    public function test_a_user_without_notifications_and_a_second_delete_event_are_fine(): void
    {
        $this->deliver((string) Str::uuid());
        $this->deliver((string) Str::uuid());

        $this->assertSame(0, Notification::ownedBy(self::DELETED)->count());
    }
}
