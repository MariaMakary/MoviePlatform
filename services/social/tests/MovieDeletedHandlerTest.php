<?php

namespace Tests;

use App\Events\VotesUpdated;
use App\Integration\Handlers\MovieDeletedHandler;
use App\Models\Candidate;
use App\Models\MovieNight;
use App\Models\Vote;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class MovieDeletedHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const JOE = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private AMQPChannel $channel;

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->setTransactionManager(new class extends DatabaseTransactionsManager
        {
            public function afterCommitCallbacksShouldBeExecuted($level)
            {
                return $level <= 1;
            }
        });
        Event::fake([VotesUpdated::class]);

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

    private function night(MovieNightStatus $status = MovieNightStatus::Open, ?string $winner = null): MovieNight
    {
        $night = MovieNight::factory()->create(['host_id' => self::HOST]);
        $night->participants()->create(['user_id' => self::HOST, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
        $night->status = $status;
        $night->winner_movie_id = $winner;
        $night->save();

        return $night;
    }

    private function candidate(MovieNight $night, string $movieId, bool $voted = true): Candidate
    {
        $candidate = $night->candidates()->create(['movie_id' => $movieId, 'added_by' => self::JOE]);

        if ($voted) {
            Vote::insert([
                'movie_night_id' => $night->id, 'user_id' => self::HOST, 'candidate_id' => $candidate->id,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        }

        return $candidate;
    }

    public function test_the_handler_is_bound(): void
    {
        $this->assertSame(MovieDeletedHandler::class, config('events.handlers.MovieDeleted'));
        $this->assertContains('movie.deleted', config('events.bindings'));
    }

    public function test_the_movie_leaves_open_shortlists_with_its_votes_and_the_totals_are_broadcast(): void
    {
        $night = $this->night();
        $inception = $this->candidate($night, self::INCEPTION);
        $arrival = $this->candidate($night, self::ARRIVAL, voted: false);

        $this->deliver(self::INCEPTION);

        $this->assertSame(['ack'], $this->answers);
        $this->assertNull($inception->fresh());
        $this->assertNotNull($arrival->fresh());
        $this->assertSame(0, Vote::where('movie_night_id', $night->id)->count());
        Event::assertDispatched(VotesUpdated::class, fn (VotesUpdated $event) => $event->movieNightId === $night->id
            && $event->totals === [['candidate_id' => $arrival->id, 'votes' => 0]]);
    }

    public function test_finalized_and_cancelled_nights_keep_their_history(): void
    {
        $finalized = $this->night(MovieNightStatus::Finalized, self::INCEPTION);
        $finalizedCandidate = $this->candidate($finalized, self::INCEPTION);
        $cancelled = $this->night(MovieNightStatus::Cancelled);
        $cancelledCandidate = $this->candidate($cancelled, self::INCEPTION);

        $this->deliver(self::INCEPTION);

        $this->assertSame(['ack'], $this->answers);
        $this->assertSame(self::INCEPTION, $finalized->fresh()->winner_movie_id);
        $this->assertNotNull($finalizedCandidate->fresh());
        $this->assertNotNull($cancelledCandidate->fresh());
        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_the_same_event_twice_or_an_unknown_movie_is_acknowledged(): void
    {
        $night = $this->night();
        $this->candidate($night, self::INCEPTION);
        $id = (string) Str::uuid();

        $this->deliver(self::INCEPTION, $id);
        $this->deliver(self::INCEPTION, $id);
        $this->deliver(self::ARRIVAL);

        $this->assertSame(['ack', 'ack', 'ack'], $this->answers);
        Event::assertDispatchedTimes(VotesUpdated::class, 1);
    }
}
