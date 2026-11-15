<?php

namespace Tests;

use App\Events\VotesUpdated;
use App\Models\Candidate;
use App\Models\MovieNight;
use App\Models\Vote;
use Firebase\JWT\JWT;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;
use RuntimeException;

class MovieNightVotingTest extends TestCase
{
    use DatabaseTransactions;

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const JOE = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const JOY = '0193a7c0-4444-7aaa-8bbb-000000000004';

    private const STRANGER = '0193a7c0-5555-7aaa-8bbb-000000000005';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private MovieNight $night;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
        Carbon::setTestNow(Carbon::parse('2026-11-15T18:00:00Z'));
        Event::fake([VotesUpdated::class]);

        $this->night = MovieNight::factory()->create(['host_id' => self::HOST]);
        $this->night->participants()->create(['user_id' => self::HOST, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
        $this->night->participants()->create(['user_id' => self::JOE, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
        $this->night->participants()->create(['user_id' => self::TONIO]);
        $this->night->participants()->create(['user_id' => self::JOY, 'status' => ParticipantStatus::Declined, 'responded_at' => Carbon::now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Someone', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function addCandidate(string $movieId, string $as = self::JOE, ?string $nightId = null): void
    {
        $nightId ??= $this->night->id;
        $this->json('POST', "/api/movie-nights/{$nightId}/candidates", ['movie_id' => $movieId], $this->auth($as));
    }

    private function vote(string $candidateId, string $as = self::JOE): void
    {
        $this->json('POST', "/api/movie-nights/{$this->night->id}/votes", ['candidate_id' => $candidateId], $this->auth($as));
    }

    private function removeCandidate(string $candidateId, string $as = self::JOE): void
    {
        $this->json('DELETE', "/api/movie-nights/{$this->night->id}/candidates/{$candidateId}", [], $this->auth($as));
    }

    private function candidate(string $movieId, string $addedBy = self::JOE, ?MovieNight $night = null): Candidate
    {
        return ($night ?? $this->night)->candidates()->create(['movie_id' => $movieId, 'added_by' => $addedBy]);
    }

    private function castVote(Candidate $candidate, string $userId): void
    {
        Vote::insert([
            'movie_night_id' => $candidate->movie_night_id,
            'user_id' => $userId,
            'candidate_id' => $candidate->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function close(MovieNightStatus $status): void
    {
        $this->night->status = $status;
        $this->night->save();
    }

    private function assertBroadcastTotals(array $totals, int $times = 1): void
    {
        Event::assertDispatchedTimes(VotesUpdated::class, $times);
        Event::assertDispatched(VotesUpdated::class, fn (VotesUpdated $event) => $event->movieNightId === $this->night->id && $event->totals === $totals);
    }

    public function test_an_accepted_participant_adds_a_movie_to_the_shortlist(): void
    {
        $this->addCandidate(self::INCEPTION);

        $this->seeStatusCode(201);
        $candidate = Candidate::where('movie_night_id', $this->night->id)->sole();
        $this->seeJson(['candidates' => [['id' => $candidate->id, 'movie_id' => self::INCEPTION, 'added_by' => self::JOE, 'votes' => 0]]]);
        $this->assertBroadcastTotals([['candidate_id' => $candidate->id, 'votes' => 0]]);
    }

    public function test_the_same_movie_can_be_proposed_only_once_per_night(): void
    {
        $this->candidate(self::INCEPTION);

        $this->addCandidate(strtoupper(self::INCEPTION), self::HOST);

        $this->seeStatusCode(422);
        $this->seeJson(['errors' => ['movie_id' => ['This movie is already on the shortlist.']]]);
        $this->assertSame(1, Candidate::where('movie_night_id', $this->night->id)->count());
        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_the_same_movie_can_be_on_the_shortlist_of_another_night(): void
    {
        $other = MovieNight::factory()->create(['host_id' => self::JOE]);
        $other->participants()->create(['user_id' => self::JOE, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);
        $this->candidate(self::INCEPTION);

        $this->addCandidate(self::INCEPTION, self::JOE, $other->id);

        $this->seeStatusCode(201);
    }

    public function test_a_participant_votes_and_can_change_the_vote(): void
    {
        $inception = $this->candidate(self::INCEPTION);
        $arrival = $this->candidate(self::ARRIVAL, self::HOST);

        $this->vote($inception->id);
        $this->seeStatusCode(200);
        $this->seeJson(['my_vote' => $inception->id, 'votes_cast' => 1]);

        $this->vote($arrival->id);
        $this->seeStatusCode(200);
        $this->seeJson(['my_vote' => $arrival->id, 'votes_cast' => 1]);

        $this->assertSame([$arrival->id], Vote::where('movie_night_id', $this->night->id)->where('user_id', self::JOE)->pluck('candidate_id')->all());
        Event::assertDispatchedTimes(VotesUpdated::class, 2);
        Event::assertDispatched(VotesUpdated::class, fn (VotesUpdated $event) => $event->totals === [
            ['candidate_id' => $inception->id, 'votes' => 0],
            ['candidate_id' => $arrival->id, 'votes' => 1],
        ]);
    }

    public function test_voting_for_the_same_movie_again_changes_nothing_and_broadcasts_nothing(): void
    {
        $inception = $this->candidate(self::INCEPTION);
        $this->castVote($inception, self::JOE);

        $this->vote(strtoupper($inception->id));

        $this->seeStatusCode(200);
        $this->seeJson(['my_vote' => $inception->id, 'votes_cast' => 1]);
        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_totals_count_every_participants_vote(): void
    {
        $inception = $this->candidate(self::INCEPTION);
        $arrival = $this->candidate(self::ARRIVAL, self::HOST);
        $this->castVote($inception, self::JOE);

        $this->vote($inception->id, self::HOST);

        $this->assertBroadcastTotals([
            ['candidate_id' => $inception->id, 'votes' => 2],
            ['candidate_id' => $arrival->id, 'votes' => 0],
        ]);
    }

    public function test_the_movie_voted_for_must_be_on_this_nights_shortlist(): void
    {
        $other = MovieNight::factory()->create(['host_id' => self::JOE]);
        $elsewhere = $this->candidate(self::INCEPTION, self::JOE, $other);

        foreach ([$elsewhere->id, '0193a7d9-9999-7aaa-8bbb-000000000999'] as $candidateId) {
            $this->vote($candidateId);

            $this->seeStatusCode(422);
            $this->seeJson(['errors' => ['candidate_id' => ['This movie is not on the shortlist of this movie night.']]]);
        }

        $this->assertSame(0, Vote::where('movie_night_id', $this->night->id)->count());
        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_the_movie_is_removed_by_who_added_it_or_by_the_host_with_its_votes(): void
    {
        $inception = $this->candidate(self::INCEPTION);
        $arrival = $this->candidate(self::ARRIVAL);
        $this->castVote($inception, self::JOE);
        $this->castVote($inception, self::HOST);

        $this->removeCandidate($inception->id, self::JOE);
        $this->seeStatusCode(200);
        $this->seeJson(['my_vote' => null, 'votes_cast' => 0]);
        $this->assertSame(0, Vote::where('movie_night_id', $this->night->id)->count());

        $this->removeCandidate($arrival->id, self::HOST);
        $this->seeStatusCode(200);
        $this->seeJson(['candidates' => []]);

        Event::assertDispatchedTimes(VotesUpdated::class, 2);
        Event::assertDispatched(VotesUpdated::class, fn (VotesUpdated $event) => $event->totals === [['candidate_id' => $arrival->id, 'votes' => 0]]);
        Event::assertDispatched(VotesUpdated::class, fn (VotesUpdated $event) => $event->totals === []);
    }

    public function test_another_participant_cannot_remove_a_movie_they_did_not_add(): void
    {
        $arrival = $this->candidate(self::ARRIVAL, self::HOST);

        $this->removeCandidate($arrival->id, self::JOE);

        $this->seeStatusCode(403);
        $this->seeJson(['message' => 'Only the host or the person who added this movie can remove it.']);
        $this->assertNotNull($arrival->fresh());
    }

    public function test_a_candidate_of_another_night_is_not_found(): void
    {
        $other = MovieNight::factory()->create(['host_id' => self::JOE]);
        $elsewhere = $this->candidate(self::INCEPTION, self::JOE, $other);

        foreach ([$elsewhere->id, '0193a7d9-9999-7aaa-8bbb-000000000999'] as $candidateId) {
            $this->removeCandidate($candidateId);

            $this->seeStatusCode(404);
            $this->seeJson(['message' => 'Candidate not found.']);
        }

        $this->assertNotNull($elsewhere->fresh());
    }

    public function test_a_user_who_is_not_invited_gets_404(): void
    {
        $inception = $this->candidate(self::INCEPTION);

        $this->addCandidate(self::ARRIVAL, self::STRANGER);
        $this->seeStatusCode(404);
        $this->seeJson(['message' => 'Movie night not found.']);

        $this->vote($inception->id, self::STRANGER);
        $this->seeStatusCode(404);

        $this->removeCandidate($inception->id, self::STRANGER);
        $this->seeStatusCode(404);

        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_an_invited_user_who_has_not_accepted_gets_403(): void
    {
        $inception = $this->candidate(self::INCEPTION);

        foreach ([self::TONIO, self::JOY] as $userId) {
            $this->addCandidate(self::ARRIVAL, $userId);
            $this->seeStatusCode(403);
            $this->seeJson(['message' => 'Accept the invitation first.']);

            $this->vote($inception->id, $userId);
            $this->seeStatusCode(403);
            $this->seeJson(['message' => 'Accept the invitation first.']);
        }

        $this->assertSame(1, Candidate::where('movie_night_id', $this->night->id)->count());
        $this->assertSame(0, Vote::where('movie_night_id', $this->night->id)->count());
        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_nothing_changes_once_the_night_is_finalized_or_cancelled(): void
    {
        $inception = $this->candidate(self::INCEPTION);

        foreach ([MovieNightStatus::Finalized, MovieNightStatus::Cancelled] as $status) {
            $this->close($status);

            $this->addCandidate(self::ARRIVAL);
            $this->seeStatusCode(422);
            $this->seeJson(['message' => 'This movie night is no longer open.']);

            $this->vote($inception->id);
            $this->seeStatusCode(422);

            $this->removeCandidate($inception->id);
            $this->seeStatusCode(422);
        }

        $this->assertSame([self::INCEPTION], Candidate::where('movie_night_id', $this->night->id)->pluck('movie_id')->all());
        $this->assertSame(0, Vote::where('movie_night_id', $this->night->id)->count());
        Event::assertNotDispatched(VotesUpdated::class);
    }

    public function test_the_movie_and_candidate_ids_are_validated(): void
    {
        $this->addCandidate('not-a-uuid');
        $this->seeStatusCode(422);
        $this->seeJsonStructure(['errors' => ['movie_id']]);

        $this->json('POST', "/api/movie-nights/{$this->night->id}/votes", [], $this->auth(self::JOE));
        $this->seeStatusCode(422);
        $this->seeJsonStructure(['errors' => ['candidate_id']]);

        $this->json('DELETE', "/api/movie-nights/{$this->night->id}/candidates/not-a-uuid", [], $this->auth(self::JOE));
        $this->seeStatusCode(404);
    }

    public function test_a_guest_gets_401(): void
    {
        $this->json('POST', "/api/movie-nights/{$this->night->id}/candidates", ['movie_id' => self::INCEPTION]);

        $this->seeStatusCode(401);
    }

    public function test_the_vote_is_saved_even_when_reverb_is_down(): void
    {
        Event::swap(new Dispatcher($this->app));
        $broadcaster = $this->createMock(BroadcastManager::class);
        $broadcaster->method('queue')->willThrowException(new RuntimeException('Connection refused'));
        $this->app->instance(BroadcastFactory::class, $broadcaster);
        Log::spy();
        $inception = $this->candidate(self::INCEPTION);

        $this->vote($inception->id);

        $this->seeStatusCode(200);
        $this->seeJson(['my_vote' => $inception->id]);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'VotesUpdated was not broadcast');
    }

    public function test_the_broadcast_goes_to_the_private_movie_night_channel(): void
    {
        $event = new VotesUpdated($this->night->id, [['candidate_id' => 'c1', 'votes' => 3]]);

        $this->assertSame("private-movie-night.{$this->night->id}", $event->broadcastOn()->name);
        $this->assertSame('VotesUpdated', $event->broadcastAs());
        $this->assertSame(['movie_night_id' => $this->night->id, 'totals' => [['candidate_id' => 'c1', 'votes' => 3]]], $event->broadcastWith());
    }
}
