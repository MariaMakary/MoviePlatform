<?php

namespace Tests;

use App\Integration\Handlers\MovieNightFinalizedHandler;
use App\Integration\Handlers\MovieNightInviteAnsweredHandler;
use App\Integration\Handlers\MovieNightInviteSentHandler;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use MoviePlatform\Common\Notifications\NotificationType;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class MovieNightNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private const NIGHT = '0193a7d3-8888-7aaa-8bbb-000000000600';

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const GUEST = '0193a7c0-1111-7aaa-8bbb-000000000003';

    private const OTHER_GUEST = '0193a7c0-1111-7aaa-8bbb-000000000004';

    private AMQPChannel $channel;

    private array $rejected = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = $this->createMock(AMQPChannel::class);
        $this->channel->method('basic_reject')->willReturnCallback(function ($tag, $requeue) {
            $this->rejected[] = $tag;
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        config(['notifications.display_timezone' => 'Asia/Beirut']);
    }

    private function deliver(string $event, array $data, ?string $id = null): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $event,
            'id' => $id ?? (string) Str::uuid(),
            'occurred_at' => '2026-11-07T10:00:00Z',
            'data' => $data,
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function inviteSent(array $data = []): array
    {
        return $data + [
            'movie_night_id' => self::NIGHT,
            'host_id' => self::HOST,
            'host_name' => 'Maria Makary',
            'user_id' => self::GUEST,
            'scheduled_at' => '2026-11-07T19:00:00Z',
        ];
    }

    private function inviteAnswered(array $data = []): array
    {
        return $data + [
            'movie_night_id' => self::NIGHT,
            'host_id' => self::HOST,
            'user_id' => self::GUEST,
            'user_name' => 'Tonio Jreige',
            'status' => 'accepted',
        ];
    }

    private function finalized(array $data = []): array
    {
        return $data + [
            'movie_night_id' => self::NIGHT,
            'host_id' => self::HOST,
            'participant_ids' => [self::GUEST, self::OTHER_GUEST],
            'winner_movie_id' => '0193a7c1-3333-7aaa-8bbb-000000000100',
            'winner_movie_title' => 'Inception',
            'scheduled_at' => '2026-11-07T19:00:00Z',
            'location' => null,
            'meeting_link' => 'https://meet.example.com/abc',
        ];
    }

    public function test_the_three_handlers_are_registered(): void
    {
        $this->assertSame(MovieNightInviteSentHandler::class, config('events.handlers.MovieNightInviteSent'));
        $this->assertSame(MovieNightInviteAnsweredHandler::class, config('events.handlers.MovieNightInviteAnswered'));
        $this->assertSame(MovieNightFinalizedHandler::class, config('events.handlers.MovieNightFinalized'));

        foreach (['movie_night.invite_sent', 'movie_night.invite_answered', 'movie_night.finalized'] as $routingKey) {
            $this->assertContains($routingKey, config('events.bindings'));
        }
    }

    public function test_an_invite_notifies_the_invited_user_with_the_local_time(): void
    {
        $this->deliver('MovieNightInviteSent', $this->inviteSent());

        $notification = Notification::ownedBy(self::GUEST)->sole();
        $this->assertSame(NotificationType::MovieNightInvite, $notification->type);
        $this->assertSame('movie_night_invite', $notification->getRawOriginal('type'));
        $this->assertSame('Maria Makary invited you to a movie night on 7 Nov 2026, 21:00', $notification->title);
        $this->assertNotSame('', $notification->body);
        $this->assertSame(['movie_night_id' => self::NIGHT], $notification->data);
        $this->assertNull($notification->read_at);

        $this->assertSame(0, Notification::ownedBy(self::HOST)->count(), 'The host gets nothing.');
    }

    public function test_an_accepted_invitation_notifies_the_host(): void
    {
        $this->deliver('MovieNightInviteAnswered', $this->inviteAnswered());

        $notification = Notification::ownedBy(self::HOST)->sole();
        $this->assertSame(NotificationType::MovieNightInviteAnswered, $notification->type);
        $this->assertSame('movie_night_invite_answered', $notification->getRawOriginal('type'));
        $this->assertSame('Tonio Jreige accepted your invitation', $notification->title);
        $this->assertSame(['movie_night_id' => self::NIGHT], $notification->data);

        $this->assertSame(0, Notification::ownedBy(self::GUEST)->count(), 'The guest who answered gets nothing.');
    }

    public function test_a_declined_invitation_notifies_the_host(): void
    {
        $this->deliver('MovieNightInviteAnswered', $this->inviteAnswered(['status' => 'declined']));

        $this->assertSame('Tonio Jreige declined your invitation', Notification::ownedBy(self::HOST)->sole()->title);
    }

    public function test_an_answer_with_an_unknown_status_creates_nothing_and_goes_to_the_dlq(): void
    {
        $id = (string) Str::uuid();

        $this->deliver('MovieNightInviteAnswered', $this->inviteAnswered(['status' => 'pending']), $id);

        $this->assertSame(0, Notification::ownedBy(self::HOST)->count());
        $this->assertSame([1], $this->rejected);
        $this->assertSame(0, DB::table('processed_events')->where('event_id', $id)->count());
    }

    public function test_finalizing_notifies_every_accepted_participant(): void
    {
        $this->deliver('MovieNightFinalized', $this->finalized());

        foreach ([self::GUEST, self::OTHER_GUEST] as $userId) {
            $notification = Notification::ownedBy($userId)->sole();
            $this->assertSame(NotificationType::MovieNightFinalized, $notification->type);
            $this->assertSame('movie_night_finalized', $notification->getRawOriginal('type'));
            $this->assertSame('Movie night: Inception won the vote', $notification->title);
            $this->assertSame(['movie_night_id' => self::NIGHT], $notification->data);
        }

        $this->assertSame(0, Notification::ownedBy(self::HOST)->count(), 'The host is not in participant_ids.');
    }

    public function test_finalizing_skips_the_host_and_repeated_ids(): void
    {
        $this->deliver('MovieNightFinalized', $this->finalized(['participant_ids' => [self::GUEST, self::GUEST, self::HOST]]));

        $this->assertSame(1, Notification::ownedBy(self::GUEST)->count());
        $this->assertSame(0, Notification::ownedBy(self::HOST)->count());
    }

    public function test_finalizing_with_no_participants_creates_nothing(): void
    {
        $this->deliver('MovieNightFinalized', $this->finalized(['participant_ids' => []]));

        $this->assertSame(0, Notification::whereIn('user_id', [self::HOST, self::GUEST, self::OTHER_GUEST])->count());
    }

    public function test_each_event_delivered_twice_creates_its_notifications_once(): void
    {
        foreach ([
            ['MovieNightInviteSent', $this->inviteSent()],
            ['MovieNightInviteAnswered', $this->inviteAnswered()],
            ['MovieNightFinalized', $this->finalized()],
        ] as [$event, $data]) {
            $id = (string) Str::uuid();
            $this->deliver($event, $data, $id);
            $this->deliver($event, $data, $id);
        }

        $this->assertSame(1, Notification::ownedBy(self::HOST)->count());
        $this->assertSame(2, Notification::ownedBy(self::GUEST)->count());
        $this->assertSame(1, Notification::ownedBy(self::OTHER_GUEST)->count());
    }
}
