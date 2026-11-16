<?php

namespace Tests;

use App\Enums\ActivityType;
use App\Integration\Handlers\ActivityHandler;
use App\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class ActivityHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private const USER = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const RATING = '0193a7d0-5555-7aaa-8bbb-000000000300';

    private const REVIEW = '0193a7d1-6666-7aaa-8bbb-000000000400';

    private const LIST = '0193a7d2-7777-7aaa-8bbb-000000000500';

    private AMQPChannel $channel;

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

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

    private function deliver(string $event, array $data, string $at = '2026-11-16T10:00:00Z', ?string $id = null): string
    {
        $id ??= (string) Str::uuid();
        $message = new AMQPMessage(json_encode([
            'event' => $event, 'id' => $id, 'occurred_at' => $at, 'data' => $data + ['unknown_field' => 'ignored'],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);

        return $id;
    }

    private function rate(int $score, string $at = '2026-11-16T10:00:00Z'): string
    {
        return $this->deliver('RatingAdded', ['rating_id' => self::RATING, 'user_id' => self::USER, 'movie_id' => self::MOVIE, 'score' => $score], $at);
    }

    private function activities(): array
    {
        return Activity::where('user_id', self::USER)->orderBy('created_at')->get()
            ->map(fn (Activity $a) => [$a->type->value, $a->subject_id, $a->movie_id, $a->data, $a->created_at->toIso8601ZuluString()])->all();
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, character_maximum_length, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'activities' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->character_maximum_length ? " ({$c->character_maximum_length})" : '').($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'id' => 'uuid', 'event_id' => 'uuid', 'user_id' => 'uuid', 'type' => 'character varying (30)',
            'subject_id' => 'uuid', 'movie_id' => 'uuid NULL', 'data' => 'jsonb', 'created_at' => 'timestamp without time zone',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'activities'"))->pluck('indexname');
        $this->assertContains('activities_event_id_unique', $indexes);
        $this->assertContains('activities_user_id_created_at_index', $indexes);
    }

    public function test_the_database_refuses_an_unknown_type(): void
    {
        $this->expectExceptionMessage('activities_type_check');

        DB::table('activities')->insert(['id' => (string) Str::uuid(), 'event_id' => (string) Str::uuid(), 'user_id' => self::USER,
            'type' => 'poke', 'subject_id' => self::MOVIE, 'created_at' => Carbon::now()]);
    }

    public function test_the_handler_is_bound_to_the_five_engagement_events(): void
    {
        foreach (['RatingAdded', 'ReviewCreated', 'ReviewUpdated', 'MovieWatched', 'ListCreated'] as $event) {
            $this->assertSame(ActivityHandler::class, config("events.handlers.$event"), $event);
        }
    }

    public function test_a_rating_adds_an_entry_with_the_score_at_the_event_time(): void
    {
        $this->rate(5, '2026-11-16T09:30:00Z');

        $this->assertSame([['rating', self::RATING, self::MOVIE, ['score' => 5], '2026-11-16T09:30:00Z']], $this->activities());
        $this->assertSame(['ack'], $this->answers);
    }

    public function test_a_changed_rating_updates_its_entry_and_moves_it_up(): void
    {
        $this->rate(5, '2026-11-16T09:00:00Z');
        $this->rate(2, '2026-11-16T11:00:00Z');

        $this->assertSame([['rating', self::RATING, self::MOVIE, ['score' => 2], '2026-11-16T11:00:00Z']], $this->activities());
    }

    public function test_a_review_adds_an_excerpt_and_an_edit_updates_it_in_place(): void
    {
        $long = str_repeat('Loved it. ', 50);
        $this->deliver('ReviewCreated', ['review_id' => self::REVIEW, 'user_id' => self::USER, 'movie_id' => self::MOVIE, 'body' => $long], '2026-11-16T09:00:00Z');

        $excerpt = Activity::sole()->data['excerpt'];
        $this->assertLessThanOrEqual(283, mb_strlen($excerpt), 'Cut to 280 characters plus "...".');
        $this->assertStringStartsWith('Loved it.', $excerpt);

        $this->deliver('ReviewUpdated', ['review_id' => self::REVIEW, 'user_id' => self::USER, 'movie_id' => self::MOVIE, 'body' => 'Even better the second time.'], '2026-11-16T12:00:00Z');

        $this->assertSame([['review', self::REVIEW, self::MOVIE, ['excerpt' => 'Even better the second time.'], '2026-11-16T09:00:00Z']], $this->activities());
    }

    public function test_an_update_of_an_unknown_review_creates_its_entry(): void
    {
        $this->deliver('ReviewUpdated', ['review_id' => self::REVIEW, 'user_id' => self::USER, 'movie_id' => self::MOVIE, 'body' => 'Written before the feed existed.']);

        $this->assertSame('review', Activity::sole()->type->value);
    }

    public function test_every_watch_adds_an_entry(): void
    {
        $data = ['user_id' => self::USER, 'movie_id' => self::MOVIE, 'watched_at' => '2026-11-15T21:00:00Z'];

        $this->deliver('MovieWatched', $data, '2026-11-15T21:05:00Z');
        $this->deliver('MovieWatched', ['watched_at' => '2026-11-16T20:00:00Z'] + $data, '2026-11-16T20:05:00Z');

        $this->assertSame([
            ['watched', self::MOVIE, self::MOVIE, ['watched_at' => '2026-11-15T21:00:00Z'], '2026-11-15T21:05:00Z'],
            ['watched', self::MOVIE, self::MOVIE, ['watched_at' => '2026-11-16T20:00:00Z'], '2026-11-16T20:05:00Z'],
        ], $this->activities());
    }

    public function test_only_public_lists_reach_the_feed(): void
    {
        $this->deliver('ListCreated', ['list_id' => self::LIST, 'user_id' => self::USER, 'title' => 'Best of Nolan', 'is_public' => true]);
        $this->deliver('ListCreated', ['list_id' => (string) Str::uuid(), 'user_id' => self::USER, 'title' => 'Secret', 'is_public' => false]);

        $this->assertSame([['list', self::LIST, null, ['title' => 'Best of Nolan'], '2026-11-16T10:00:00Z']], $this->activities());
    }

    public function test_the_same_event_twice_gives_one_entry(): void
    {
        $data = ['review_id' => self::REVIEW, 'user_id' => self::USER, 'movie_id' => self::MOVIE, 'body' => 'Great.'];
        $id = (string) Str::uuid();

        $this->deliver('ReviewCreated', $data, id: $id);
        $this->deliver('ReviewCreated', $data, id: $id);

        $this->assertSame(1, Activity::count());
        $this->assertSame(['ack', 'ack'], $this->answers);
    }

    public function test_movie_deleted_removes_the_movies_entries_only(): void
    {
        $this->rate(4);
        $this->deliver('ListCreated', ['list_id' => self::LIST, 'user_id' => self::USER, 'title' => 'Best of Nolan', 'is_public' => true]);

        $this->deliver('MovieDeleted', ['movie_id' => self::MOVIE]);

        $this->assertSame([ActivityType::List], Activity::pluck('type')->all());
    }
}
