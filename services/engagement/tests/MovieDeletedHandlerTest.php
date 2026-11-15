<?php

namespace Tests;

use App\Integration\Handlers\MovieDeletedHandler;
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

class MovieDeletedHandlerTest extends TestCase
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

    private function deliver(string $movieId, ?string $id = null): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => 'MovieDeleted',
            'id' => $id ?? (string) Str::uuid(),
            'occurred_at' => '2026-11-15T10:00:00Z',
            'data' => ['movie_id' => $movieId],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function everythingAbout(string $movieId, MovieList $list): void
    {
        foreach ([self::TONIO, self::JOY] as $userId) {
            Rating::forceCreate(['user_id' => $userId, 'movie_id' => $movieId, 'score' => 4]);
            Review::forceCreate(['user_id' => $userId, 'movie_id' => $movieId, 'body' => 'Great movie.']);
            WatchlistItem::forceCreate(['user_id' => $userId, 'movie_id' => $movieId]);
            WatchHistoryEntry::forceCreate(['user_id' => $userId, 'movie_id' => $movieId, 'watched_at' => Carbon::now()]);
        }
        ListItem::create(['list_id' => $list->id, 'movie_id' => $movieId]);
    }

    private function countsOf(string $movieId): array
    {
        return [
            'ratings' => Rating::where('movie_id', $movieId)->count(),
            'reviews' => Review::where('movie_id', $movieId)->count(),
            'watchlist' => WatchlistItem::where('movie_id', $movieId)->count(),
            'history' => WatchHistoryEntry::where('movie_id', $movieId)->count(),
            'list_items' => ListItem::where('movie_id', $movieId)->count(),
        ];
    }

    public function test_the_handler_is_bound(): void
    {
        $this->assertSame(MovieDeletedHandler::class, config('events.handlers.MovieDeleted'));
        $this->assertContains('movie.deleted', config('events.bindings'));
    }

    public function test_every_trace_of_the_movie_is_removed_and_other_movies_stay(): void
    {
        $list = MovieList::forceCreate(['user_id' => self::TONIO, 'title' => 'Favourites', 'is_public' => true]);
        $this->everythingAbout(self::INCEPTION, $list);
        $this->everythingAbout(self::ARRIVAL, $list);

        $this->deliver(self::INCEPTION);

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame(['ratings' => 0, 'reviews' => 0, 'watchlist' => 0, 'history' => 0, 'list_items' => 0], $this->countsOf(self::INCEPTION));
        $this->assertSame(['ratings' => 2, 'reviews' => 2, 'watchlist' => 2, 'history' => 2, 'list_items' => 1], $this->countsOf(self::ARRIVAL));
        $this->assertNotNull($list->fresh());
    }

    public function test_the_same_event_twice_or_an_unknown_movie_is_acknowledged(): void
    {
        $id = (string) Str::uuid();

        $this->deliver(self::INCEPTION, $id);
        $this->deliver(self::INCEPTION, $id);
        $this->deliver(self::ARRIVAL);

        $this->assertSame(['ack', 'ack', 'ack'], $this->answers);
    }
}
