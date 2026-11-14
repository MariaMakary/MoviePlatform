<?php

namespace Tests;

use App\Integration\Handlers\MovieWatchedHandler;
use App\Integration\Handlers\RatingAddedHandler;
use App\Integration\Handlers\UserGenresUpdatedHandler;
use App\Models\UserTasteProfile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class TasteProfileTest extends TestCase
{
    use DatabaseTransactions;

    private const USER = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const BARBIE = '0193a7c1-3333-7aaa-8bbb-000000000200';

    private const SCI_FI = '0193a7b0-2222-7aaa-8bbb-000000000010';

    private const ACTION = '0193a7b0-2222-7aaa-8bbb-000000000011';

    private const COMEDY = '0193a7b0-2222-7aaa-8bbb-000000000012';

    private const DICAPRIO = '0193a7b5-4444-7aaa-8bbb-000000000001';

    private const NOLAN = '0193a7b5-4444-7aaa-8bbb-000000000002';

    private const ROBBIE = '0193a7b5-4444-7aaa-8bbb-000000000003';

    private AMQPChannel $channel;

    /** 'ack' or 'reject' (no requeue = to the DLQ), what the consumer answered RabbitMQ. */
    private array $answers = [];

    private bool $catalogDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.catalog.url' => 'http://catalog.test',
            'internal.service_token' => 'test-service-token',
        ]);

        $features = [
            self::INCEPTION => ['genre_ids' => [self::SCI_FI, self::ACTION], 'cast_ids' => [self::DICAPRIO], 'director_ids' => [self::NOLAN]],
            self::BARBIE => ['genre_ids' => [self::COMEDY], 'cast_ids' => [self::ROBBIE], 'director_ids' => []],
        ];

        Http::fake(function (Request $request) use ($features) {
            if ($this->catalogDown) {
                return Http::response(['message' => 'Server error.'], 500);
            }

            preg_match('#/api/internal/movies/([^/]+)/features$#', $request->url(), $match);

            return isset($features[$match[1] ?? ''])
                ? Http::response(['data' => $features[$match[1]]])
                : Http::response(['message' => 'Movie not found.'], 404);
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

    private function deliver(string $event, array $data, ?string $id = null): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $event,
            'id' => $id ?? (string) Str::uuid(),
            'occurred_at' => '2026-11-14T10:00:00Z',
            'data' => $data + ['unknown_field' => 'ignored'],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function rate(string $movieId, int $score, ?string $id = null): void
    {
        $this->deliver('RatingAdded', ['rating_id' => (string) Str::uuid(), 'user_id' => self::USER, 'movie_id' => $movieId, 'score' => $score], $id);
    }

    private function watch(string $movieId, ?string $id = null): void
    {
        $this->deliver('MovieWatched', ['user_id' => self::USER, 'movie_id' => $movieId, 'watched_at' => '2026-11-13T21:00:00Z'], $id);
    }

    private function profile(): ?UserTasteProfile
    {
        return UserTasteProfile::where('user_id', self::USER)->first();
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'user_taste_profiles' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'id' => 'uuid',
            'user_id' => 'uuid',
            'genre_weights' => 'jsonb',
            'people_weights' => 'jsonb',
            'seen_movie_ids' => 'jsonb',
            'favorite_genre_ids' => 'jsonb',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns);

        $this->assertContains('user_taste_profiles_user_id_unique', collect(DB::select("select indexname from pg_indexes where tablename = 'user_taste_profiles'"))->pluck('indexname'));
    }

    public function test_the_three_handlers_are_bound(): void
    {
        $this->assertSame(RatingAddedHandler::class, config('events.handlers.RatingAdded'));
        $this->assertSame(MovieWatchedHandler::class, config('events.handlers.MovieWatched'));
        $this->assertSame(UserGenresUpdatedHandler::class, config('events.handlers.UserGenresUpdated'));
    }

    public function test_a_rating_adds_score_minus_3_to_the_movies_genres_and_people(): void
    {
        $this->rate(self::INCEPTION, 5);

        $profile = $this->profile();
        $this->assertSame([self::SCI_FI => 2, self::ACTION => 2], $profile->genre_weights);
        $this->assertSame([self::DICAPRIO => 2, self::NOLAN => 2], $profile->people_weights);
        $this->assertSame([self::INCEPTION => 5], $profile->seen_movie_ids);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://catalog.test/api/internal/movies/'.self::INCEPTION.'/features'
            && $request->hasHeader('X-Service-Token', 'test-service-token'));
    }

    public function test_changing_a_rating_from_5_to_2_is_as_if_it_had_been_2_from_the_start(): void
    {
        $this->rate(self::INCEPTION, 5);
        $this->rate(self::INCEPTION, 2);

        $profile = $this->profile();
        $this->assertSame([self::SCI_FI => -1, self::ACTION => -1], $profile->genre_weights);
        $this->assertSame([self::DICAPRIO => -1, self::NOLAN => -1], $profile->people_weights);
        $this->assertSame([self::INCEPTION => 2], $profile->seen_movie_ids);
    }

    public function test_a_neutral_3_adds_nothing_and_does_not_call_catalog(): void
    {
        $this->rate(self::INCEPTION, 3);

        $this->assertSame([], $this->profile()->genre_weights);
        $this->assertSame([self::INCEPTION => 3], $this->profile()->seen_movie_ids);
        Http::assertNothingSent();
    }

    public function test_the_same_event_twice_is_applied_once(): void
    {
        $id = (string) Str::uuid();

        $this->rate(self::INCEPTION, 5, $id);
        $this->rate(self::INCEPTION, 5, $id);

        $this->assertSame([self::SCI_FI => 2, self::ACTION => 2], $this->profile()->genre_weights);
        Http::assertSentCount(1);
    }

    public function test_watching_adds_1_once_per_movie(): void
    {
        $this->watch(self::INCEPTION);
        $this->watch(self::INCEPTION);

        $profile = $this->profile();
        $this->assertSame([self::SCI_FI => 1, self::ACTION => 1], $profile->genre_weights);
        $this->assertSame([self::INCEPTION => null], $profile->seen_movie_ids);
    }

    public function test_watching_then_rating_adds_both(): void
    {
        $this->watch(self::INCEPTION);
        $this->rate(self::INCEPTION, 4);

        $this->assertSame([self::SCI_FI => 2, self::ACTION => 2], $this->profile()->genre_weights);
        $this->assertSame([self::INCEPTION => 4], $this->profile()->seen_movie_ids);
    }

    public function test_a_movie_already_rated_adds_nothing_when_watched(): void
    {
        $this->rate(self::INCEPTION, 4);
        $this->watch(self::INCEPTION);

        $this->assertSame([self::SCI_FI => 1, self::ACTION => 1], $this->profile()->genre_weights);
        $this->assertSame([self::INCEPTION => 4], $this->profile()->seen_movie_ids, 'The score is kept.');
    }

    public function test_weights_from_several_movies_add_up_and_zeros_are_removed(): void
    {
        $this->rate(self::INCEPTION, 5);
        $this->rate(self::BARBIE, 1);
        $this->rate(self::INCEPTION, 3);

        $profile = $this->profile();
        $this->assertSame([self::COMEDY => -2], $profile->genre_weights);
        $this->assertSame([self::ROBBIE => -2], $profile->people_weights);
        $this->assertSame([self::INCEPTION => 3, self::BARBIE => 1], $profile->seen_movie_ids);
    }

    public function test_favorite_genres_replace_the_old_list_and_never_double(): void
    {
        $data = ['user_id' => self::USER, 'genre_ids' => [self::SCI_FI, self::COMEDY]];

        $this->deliver('UserGenresUpdated', $data);
        $this->deliver('UserGenresUpdated', $data);
        $this->assertSame([self::SCI_FI, self::COMEDY], $this->profile()->favorite_genre_ids);

        $this->deliver('UserGenresUpdated', ['user_id' => self::USER, 'genre_ids' => []]);
        $this->assertSame([], $this->profile()->favorite_genre_ids);
        $this->assertSame([], $this->profile()->genre_weights, 'Favorites are not weights: the +3 is applied when scoring (1245).');
    }

    public function test_a_movie_catalog_no_longer_has_is_skipped(): void
    {
        $unknown = '0193a7c1-3333-7aaa-8bbb-000000000999';

        $this->rate($unknown, 5);
        $this->watch($unknown);

        $this->assertSame(['ack', 'ack'], $this->answers);
        $profile = $this->profile();
        $this->assertSame([], $profile->genre_weights);
        $this->assertSame([], $profile->seen_movie_ids, 'Not marked as seen, so it can count if it comes back.');
    }

    public function test_catalog_down_changes_nothing_and_the_event_goes_to_the_dlq(): void
    {
        $this->catalogDown = true;

        $this->rate(self::INCEPTION, 5);

        $this->assertSame(['reject'], $this->answers);
        $this->assertNull($this->profile(), 'Rolled back with the consumer transaction.');
        Http::assertSentCount(4); // the first try and 3 retries
    }
}
