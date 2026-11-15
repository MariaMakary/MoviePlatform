<?php

namespace Tests;

use App\Integration\Handlers\UserBlockedHandler;
use App\Integration\Handlers\UserDeletedHandler;
use App\Integration\Handlers\UserUnblockedHandler;
use App\Models\ListItem;
use App\Models\MovieList;
use App\Models\Rating;
use App\Models\Review;
use App\Models\WatchHistoryEntry;
use App\Models\WatchlistItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class UserEventHandlersTest extends TestCase
{
    use DatabaseTransactions;

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const JOY = '0193a7c0-4444-7aaa-8bbb-000000000004';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-3333-7aaa-8bbb-000000000200';

    private AMQPChannel $channel;

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

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

    private function deliver(string $event, string $userId, ?string $id = null): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $event,
            'id' => $id ?? (string) Str::uuid(),
            'occurred_at' => '2026-11-15T10:00:00Z',
            'data' => ['user_id' => $userId],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function review(string $userId, string $movieId, bool $hidden = false): Review
    {
        return Review::forceCreate([
            'user_id' => $userId, 'movie_id' => $movieId, 'body' => 'Great movie.', 'is_hidden' => $hidden,
            'created_at' => Carbon::parse('2026-11-01T10:00:00Z'), 'updated_at' => Carbon::parse('2026-11-01T10:00:00Z'),
        ]);
    }

    private function publicList(string $userId): MovieList
    {
        $list = MovieList::forceCreate(['user_id' => $userId, 'title' => 'Favourites', 'is_public' => true]);
        ListItem::create(['list_id' => $list->id, 'movie_id' => self::INCEPTION]);

        return $list;
    }

    private function everythingOf(string $userId): void
    {
        Rating::forceCreate(['user_id' => $userId, 'movie_id' => self::INCEPTION, 'score' => 5]);
        $this->review($userId, self::INCEPTION);
        WatchlistItem::forceCreate(['user_id' => $userId, 'movie_id' => self::ARRIVAL]);
        WatchHistoryEntry::forceCreate(['user_id' => $userId, 'movie_id' => self::INCEPTION, 'watched_at' => Carbon::now()]);
        $this->publicList($userId);
    }

    private function countsOf(string $userId): array
    {
        return [
            'ratings' => Rating::where('user_id', $userId)->count(),
            'reviews' => Review::where('user_id', $userId)->count(),
            'watchlist' => WatchlistItem::where('user_id', $userId)->count(),
            'history' => WatchHistoryEntry::where('user_id', $userId)->count(),
            'lists' => MovieList::where('user_id', $userId)->count(),
            'list_items' => ListItem::whereIn('list_id', MovieList::where('user_id', $userId)->select('id'))->count(),
        ];
    }

    public function test_the_handlers_are_bound(): void
    {
        $this->assertSame(UserBlockedHandler::class, config('events.handlers.UserBlocked'));
        $this->assertSame(UserUnblockedHandler::class, config('events.handlers.UserUnblocked'));
        $this->assertSame(UserDeletedHandler::class, config('events.handlers.UserDeleted'));
        foreach (['user.blocked', 'user.unblocked', 'user.deleted'] as $routingKey) {
            $this->assertContains($routingKey, config('events.bindings'));
        }
    }

    public function test_blocking_a_user_hides_their_reviews_and_lists_only(): void
    {
        $review = $this->review(self::TONIO, self::INCEPTION);
        $list = $this->publicList(self::TONIO);
        $othersReview = $this->review(self::JOY, self::INCEPTION);
        $othersList = $this->publicList(self::JOY);

        $this->deliver('UserBlocked', self::TONIO);

        $this->assertSame(['ack'], $this->answers);
        $this->assertTrue($review->fresh()->author_blocked);
        $this->assertTrue($list->fresh()->author_blocked);
        $this->assertFalse($othersReview->fresh()->author_blocked);
        $this->assertFalse($othersList->fresh()->author_blocked);
        $this->assertSame('2026-11-01T10:00:00Z', $review->fresh()->updated_at->toIso8601ZuluString());
        $this->assertSame([$othersReview->id], Review::visible()->where('movie_id', self::INCEPTION)->pluck('id')->all());
    }

    public function test_unblocking_brings_the_content_back_but_an_admin_hidden_review_stays_hidden(): void
    {
        $review = $this->review(self::TONIO, self::INCEPTION);
        $hidden = $this->review(self::TONIO, self::ARRIVAL, hidden: true);
        $list = $this->publicList(self::TONIO);

        $this->deliver('UserBlocked', self::TONIO);
        $this->deliver('UserUnblocked', self::TONIO);

        $this->assertSame(['ack', 'ack'], $this->answers);
        $this->assertFalse($review->fresh()->author_blocked);
        $this->assertFalse($hidden->fresh()->author_blocked);
        $this->assertTrue($hidden->fresh()->is_hidden);
        $this->assertSame([$review->id], Review::visible()->where('user_id', self::TONIO)->pluck('id')->all());
        $this->assertTrue(MovieList::publiclyVisible()->whereKey($list->id)->exists());
    }

    public function test_deleting_a_user_removes_all_their_data_and_nobody_elses(): void
    {
        $this->everythingOf(self::TONIO);
        $this->everythingOf(self::JOY);

        $this->deliver('UserDeleted', self::TONIO);

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame(['ratings' => 0, 'reviews' => 0, 'watchlist' => 0, 'history' => 0, 'lists' => 0, 'list_items' => 0], $this->countsOf(self::TONIO));
        $this->assertSame(['ratings' => 1, 'reviews' => 1, 'watchlist' => 1, 'history' => 1, 'lists' => 1, 'list_items' => 1], $this->countsOf(self::JOY));
    }

    public function test_the_same_event_delivered_twice_has_no_double_effect(): void
    {
        $review = $this->review(self::TONIO, self::INCEPTION);
        $blockedId = (string) Str::uuid();

        $this->deliver('UserBlocked', self::TONIO, $blockedId);
        $this->deliver('UserUnblocked', self::TONIO);
        $this->deliver('UserBlocked', self::TONIO, $blockedId);

        $this->assertSame(['ack', 'ack', 'ack'], $this->answers);
        $this->assertFalse($review->fresh()->author_blocked);

        $deletedId = (string) Str::uuid();
        $this->deliver('UserDeleted', self::TONIO, $deletedId);
        $this->deliver('UserDeleted', self::TONIO, $deletedId);

        $this->assertSame(['ack', 'ack', 'ack', 'ack', 'ack'], $this->answers);
        $this->assertNull($review->fresh());
    }

    public function test_a_user_without_any_data_is_acknowledged(): void
    {
        foreach (['UserBlocked', 'UserUnblocked', 'UserDeleted'] as $event) {
            $this->deliver($event, self::TONIO);
        }

        $this->assertSame(['ack', 'ack', 'ack'], $this->answers);
    }
}
