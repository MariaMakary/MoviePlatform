<?php

namespace Tests;

use App\Models\Recommendation;
use App\Models\UserTasteProfile;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class RecommendationRefreshTest extends TestCase
{
    use DatabaseTransactions;

    private const USER = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const SCI_FI = '0193a7b0-2222-7aaa-8bbb-000000000010';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private const OLD_PICK = '0193a7c1-5555-7aaa-8bbb-000000000099';

    private array $answers = [];

    private bool $discoverDown = false;

    private AMQPChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        config(['internal.services.catalog' => 'http://catalog.test', 'internal.service_token' => 'test-service-token']);

        DB::connection()->setTransactionManager(new class extends DatabaseTransactionsManager
        {
            public function afterCommitCallbacksShouldBeExecuted($level)
            {
                return $level <= 1;
            }
        });

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/api/internal/discover' && $this->discoverDown => Http::response(['message' => 'Server error.'], 500),
                $path === '/api/internal/discover' => Http::response(['data' => [[
                    'id' => self::ARRIVAL, 'title' => 'Arrival', 'poster_url' => null, 'year' => 2016, 'release_date' => '2016-11-11',
                    'vote_average' => 7.6, 'genres' => ['Science Fiction'],
                ]]]),
                $path === '/api/genres' => Http::response(['data' => [['id' => self::SCI_FI, 'name' => 'Science Fiction']]]),
                str_ends_with($path, '/similar') => Http::response(['data' => []]),
                $path === '/api/internal/movies' => Http::response(['data' => []]),
                default => Http::response(['data' => ['genre_ids' => [self::SCI_FI], 'cast_ids' => [], 'director_ids' => []]]),
            };
        });

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

    private function deliver(string $event, array $data): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $event,
            'id' => (string) Str::uuid(),
            'occurred_at' => '2026-11-15T10:00:00Z',
            'data' => $data,
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function picks(): array
    {
        return Recommendation::forUser(self::USER)->pluck('movie_id')->all();
    }

    public function test_a_rating_recomputes_the_users_recommendations_after_the_event_commits(): void
    {
        $this->deliver('RatingAdded', ['rating_id' => (string) Str::uuid(), 'user_id' => self::USER, 'movie_id' => self::INCEPTION, 'score' => 5]);

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame([self::ARRIVAL], $this->picks());
    }

    public function test_a_watch_and_new_favorite_genres_recompute_too(): void
    {
        $this->deliver('MovieWatched', ['user_id' => self::USER, 'movie_id' => self::INCEPTION, 'watched_at' => '2026-11-15T09:00:00Z']);
        $this->assertSame([self::ARRIVAL], $this->picks());

        Recommendation::forUser(self::USER)->delete();
        $this->deliver('UserGenresUpdated', ['user_id' => self::USER, 'genre_ids' => [self::SCI_FI]]);
        $this->assertSame([self::ARRIVAL], $this->picks());

        $this->assertSame(['ack', 'ack'], $this->answers);
    }

    public function test_catalog_failing_during_the_recompute_keeps_the_profile_and_the_old_picks_and_acks_the_event(): void
    {
        Recommendation::forceCreate(['user_id' => self::USER, 'movie_id' => self::OLD_PICK, 'score' => 0.9, 'reason' => 'Old pick']);
        $this->discoverDown = true;
        Log::spy();

        $this->deliver('RatingAdded', ['rating_id' => (string) Str::uuid(), 'user_id' => self::USER, 'movie_id' => self::INCEPTION, 'score' => 5]);

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame([self::SCI_FI => 2], UserTasteProfile::where('user_id', self::USER)->first()->genre_weights);
        $this->assertSame([self::OLD_PICK], $this->picks());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'Recommendations were not recomputed');
    }
}
