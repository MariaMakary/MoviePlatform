<?php

namespace Tests;

use App\Enums\MovieNightStatus;
use App\Enums\ParticipantStatus;
use App\Models\Candidate;
use App\Models\MovieNight;
use App\Models\Participant;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class MovieNightTablesTest extends TestCase
{
    use DatabaseTransactions;

    private const HOST = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MARIA = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const INCEPTION = '0193a7b0-4444-7aaa-8bbb-000000000010';

    private const ARRIVAL = '0193a7b0-4444-7aaa-8bbb-000000000011';

    private function columns(string $table): array
    {
        return collect(DB::select(
            "select column_name, data_type, character_maximum_length, is_nullable, column_default from information_schema.columns
             where table_schema = current_schema() and table_name = ? order by ordinal_position",
            [$table],
        ))->mapWithKeys(fn ($c) => [$c->column_name => implode(' ', array_filter([
            $c->data_type,
            $c->character_maximum_length ? "({$c->character_maximum_length})" : null,
            $c->is_nullable === 'YES' ? 'NULL' : null,
            $c->column_default !== null ? 'default '.trim(Str::before($c->column_default, '::'), "'") : null,
        ]))])->all();
    }

    private function primaryKey(string $table): array
    {
        return collect(DB::select(
            "select a.attname from pg_index i join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any(i.indkey)
             where i.indrelid = ?::regclass and i.indisprimary order by array_position(i.indkey, a.attnum)",
            [$table],
        ))->pluck('attname')->all();
    }

    private function assertRefused(callable $query, string $sqlState): void
    {
        try {
            DB::transaction($query);
            $this->fail("The database accepted a row it should refuse (expected SQLSTATE $sqlState).");
        } catch (QueryException $e) {
            $this->assertSame($sqlState, $e->getCode());
        }
    }

    private function night(): MovieNight
    {
        return MovieNight::factory()->create(['host_id' => self::HOST]);
    }

    public function test_the_columns_match_the_erd(): void
    {
        $this->assertSame([
            'id' => 'uuid',
            'host_id' => 'uuid',
            'title' => 'character varying (100)',
            'description' => 'text NULL',
            'scheduled_at' => 'timestamp without time zone',
            'location' => 'character varying (255) NULL',
            'meeting_link' => 'character varying (255) NULL',
            'status' => 'character varying (20) default open',
            'winner_movie_id' => 'uuid NULL',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $this->columns('movie_nights'));

        $this->assertSame([
            'movie_night_id' => 'uuid',
            'user_id' => 'uuid',
            'status' => 'character varying (20) default pending',
            'responded_at' => 'timestamp without time zone NULL',
            'created_at' => 'timestamp without time zone',
        ], $this->columns('participants'));

        $this->assertSame([
            'id' => 'uuid',
            'movie_night_id' => 'uuid',
            'movie_id' => 'uuid',
            'added_by' => 'uuid',
            'created_at' => 'timestamp without time zone',
        ], $this->columns('candidates'));

        $this->assertSame([
            'movie_night_id' => 'uuid',
            'user_id' => 'uuid',
            'candidate_id' => 'uuid',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $this->columns('votes'));
    }

    public function test_the_primary_keys_match_the_erd(): void
    {
        $this->assertSame(['id'], $this->primaryKey('movie_nights'));
        $this->assertSame(['movie_night_id', 'user_id'], $this->primaryKey('participants'));
        $this->assertSame(['id'], $this->primaryKey('candidates'));
        $this->assertSame(['movie_night_id', 'user_id'], $this->primaryKey('votes'));
    }

    public function test_a_new_night_is_open_and_a_new_participant_is_pending(): void
    {
        $night = $this->night();
        $night->participants()->create(['user_id' => self::MARIA]);

        $night = $night->fresh();
        $this->assertTrue(Str::isUuid($night->id));
        $this->assertSame(MovieNightStatus::Open, $night->status);
        $this->assertSame(ParticipantStatus::Pending, $night->participants()->first()->status);
        $this->assertNull($night->participants()->first()->responded_at);
    }

    public function test_unknown_statuses_are_refused_by_the_database(): void
    {
        $night = $this->night();

        $this->assertRefused(fn () => DB::table('movie_nights')->where('id', $night->id)->update(['status' => 'postponed']), '23514');
        $this->assertRefused(fn () => DB::table('participants')->insert([
            'movie_night_id' => $night->id, 'user_id' => self::MARIA, 'status' => 'maybe', 'created_at' => Carbon::now(),
        ]), '23514');
    }

    public function test_the_statuses_are_read_as_enums_and_stored_as_strings(): void
    {
        $night = $this->night();
        $night->status = MovieNightStatus::Finalized;
        $night->save();
        $night->participants()->create(['user_id' => self::MARIA, 'status' => ParticipantStatus::Accepted, 'responded_at' => Carbon::now()]);

        $this->assertSame(MovieNightStatus::Finalized, $night->fresh()->status);
        $this->assertSame('finalized', DB::table('movie_nights')->where('id', $night->id)->value('status'));
        $this->assertSame(ParticipantStatus::Accepted, $night->participants()->first()->status);
        $this->assertSame('accepted', DB::table('participants')->where('movie_night_id', $night->id)->value('status'));
    }

    public function test_the_relationships_load_the_rows_of_their_own_night(): void
    {
        $night = $this->night();
        $other = $this->night();

        $night->participants()->create(['user_id' => self::HOST, 'status' => ParticipantStatus::Accepted]);
        $night->participants()->create(['user_id' => self::MARIA]);
        $other->participants()->create(['user_id' => self::TONIO]);
        $inception = $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);
        $night->candidates()->create(['movie_id' => self::ARRIVAL, 'added_by' => self::MARIA]);
        $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $inception->id]);

        $night = $night->fresh();
        $this->assertEqualsCanonicalizing([self::HOST, self::MARIA], $night->participants->pluck('user_id')->all());
        $this->assertEqualsCanonicalizing([self::INCEPTION, self::ARRIVAL], $night->candidates->pluck('movie_id')->all());
        $this->assertSame([self::MARIA], $night->votes->pluck('user_id')->all());
        $this->assertSame([self::MARIA], $inception->votes->pluck('user_id')->all());

        $vote = $night->votes->first();
        $this->assertSame($night->id, $vote->movieNight->id);
        $this->assertSame(self::INCEPTION, $vote->candidate->movie_id);
        $this->assertSame($night->id, Participant::where('user_id', self::MARIA)->first()->movieNight->id);
        $this->assertSame($night->id, $inception->movieNight->id);
    }

    public function test_a_user_is_invited_once_per_night(): void
    {
        $night = $this->night();
        $night->participants()->create(['user_id' => self::MARIA]);

        $this->assertRefused(fn () => $night->participants()->create(['user_id' => self::MARIA]), '23505');
        $this->night()->participants()->create(['user_id' => self::MARIA]);
        $this->assertSame(2, Participant::where('user_id', self::MARIA)->count());
    }

    public function test_a_movie_is_a_candidate_once_per_night(): void
    {
        $night = $this->night();
        $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);

        $this->assertRefused(fn () => $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::MARIA]), '23505');
        $this->night()->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);
        $this->assertSame(2, Candidate::where('movie_id', self::INCEPTION)->count());
    }

    public function test_a_second_vote_by_the_same_user_on_the_same_night_is_refused(): void
    {
        $night = $this->night();
        $inception = $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);
        $arrival = $night->candidates()->create(['movie_id' => self::ARRIVAL, 'added_by' => self::HOST]);
        $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $inception->id]);

        $this->assertRefused(fn () => $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $arrival->id]), '23505');
        $this->assertRefused(fn () => $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $inception->id]), '23505');

        $night->votes()->create(['user_id' => self::TONIO, 'candidate_id' => $arrival->id]);
        $this->assertSame(2, Vote::where('movie_night_id', $night->id)->count());
    }

    public function test_a_vote_can_be_moved_to_another_candidate(): void
    {
        $night = $this->night();
        $inception = $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);
        $arrival = $night->candidates()->create(['movie_id' => self::ARRIVAL, 'added_by' => self::HOST]);
        $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $inception->id]);

        Vote::where('movie_night_id', $night->id)->where('user_id', self::MARIA)->update(['candidate_id' => $arrival->id]);

        $this->assertSame([$arrival->id], Vote::where('movie_night_id', $night->id)->pluck('candidate_id')->all());
    }

    public function test_a_vote_must_point_to_an_existing_candidate(): void
    {
        $night = $this->night();

        $this->assertRefused(fn () => $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => (string) Str::orderedUuid()]), '23503');
    }

    public function test_deleting_a_night_deletes_its_participants_candidates_and_votes(): void
    {
        $night = $this->night();
        $night->participants()->create(['user_id' => self::MARIA]);
        $candidate = $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);
        $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $candidate->id]);

        $night->delete();

        $this->assertSame(0, Participant::where('movie_night_id', $night->id)->count());
        $this->assertSame(0, Candidate::where('movie_night_id', $night->id)->count());
        $this->assertSame(0, Vote::where('movie_night_id', $night->id)->count());
    }

    public function test_removing_a_candidate_removes_its_votes(): void
    {
        $night = $this->night();
        $candidate = $night->candidates()->create(['movie_id' => self::INCEPTION, 'added_by' => self::HOST]);
        $night->votes()->create(['user_id' => self::MARIA, 'candidate_id' => $candidate->id]);

        $candidate->delete();

        $this->assertSame(0, Vote::where('movie_night_id', $night->id)->count());
    }
}
