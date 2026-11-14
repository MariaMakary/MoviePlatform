<?php

namespace Tests;

use App\Integration\Clients\CatalogUnavailable;
use App\Models\Recommendation;
use App\Models\UserTasteProfile;
use App\Services\RecommendationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RecommendationScoringTest extends TestCase
{
    use DatabaseTransactions;

    private const USER = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const SCI_FI = '0193a7b0-2222-7aaa-8bbb-000000000010';

    private const COMEDY = '0193a7b0-2222-7aaa-8bbb-000000000012';

    private const HORROR = '0193a7b0-2222-7aaa-8bbb-000000000013';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const NOLAN = '0193a7b5-4444-7aaa-8bbb-000000000002';

    /** Movie cards Catalog knows, by id. */
    private array $cards = [];

    /** Catalog movie id => similar movie ids. */
    private array $similar = [];

    /** Catalog movie id => people ids (cast + directors). */
    private array $people = [];

    private bool $catalogDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.catalog.url' => 'http://catalog.test', 'internal.service_token' => 'test-service-token']);

        $this->card(self::INCEPTION, 'Inception', ['Science Fiction'], 8.4);

        Http::fake(function (Request $request) {
            if ($this->catalogDown) {
                return Http::response(['message' => 'Server error.'], 500);
            }

            $path = parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return match (true) {
                $path === '/api/genres' => Http::response(['data' => [
                    ['id' => self::COMEDY, 'name' => 'Comedy'], ['id' => self::HORROR, 'name' => 'Horror'], ['id' => self::SCI_FI, 'name' => 'Science Fiction'],
                ]]),
                $path === '/api/internal/discover' => Http::response(['data' => $this->discover($query)]),
                $path === '/api/internal/movies' => Http::response(['data' => array_values(array_intersect_key($this->cards, array_flip(explode(',', $query['ids']))))]),
                (bool) preg_match('#^/api/internal/movies/([^/]+)/similar$#', $path, $m) => Http::response(['data' => array_map(fn ($id) => $this->cards[$id], $this->similar[$m[1]] ?? [])]),
                (bool) preg_match('#^/api/internal/movies/([^/]+)/features$#', $path, $m) => Http::response(['data' => ['genre_ids' => [], 'cast_ids' => $this->people[$m[1]] ?? [], 'director_ids' => []]]),
                default => Http::response(['message' => 'Not found.'], 404),
            };
        });
    }

    private function card(string $id, string $title, array $genres, ?float $vote = 7.0): void
    {
        $this->cards[$id] = ['id' => $id, 'title' => $title, 'poster_url' => null, 'year' => 2020, 'release_date' => '2020-01-01', 'vote_average' => $vote, 'genres' => $genres];
    }

    // Catalog's discover: cards in any of the genre ids, without the excluded ones, in insertion order (= popularity).
    private function discover(array $query): array
    {
        $names = array_map(fn ($id) => [self::SCI_FI => 'Science Fiction', self::COMEDY => 'Comedy', self::HORROR => 'Horror'][$id] ?? null, explode(',', $query['genre_ids'] ?? ''));
        $exclude = array_flip(array_filter(explode(',', $query['exclude_ids'] ?? '')));

        return array_values(array_filter($this->cards, fn ($card) => ! isset($exclude[$card['id']]) && array_intersect($names, $card['genres']) !== []));
    }

    private function profile(array $attributes): UserTasteProfile
    {
        return UserTasteProfile::forceCreate(['user_id' => self::USER] + $attributes);
    }

    private function id(int $n): string
    {
        return sprintf('0193a7c1-5555-7aaa-8bbb-%012d', $n);
    }

    private function service(): RecommendationService
    {
        return app(RecommendationService::class);
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, numeric_precision, numeric_scale, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'recommendations' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->data_type === 'numeric' ? " ({$c->numeric_precision},{$c->numeric_scale})" : '').($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'id' => 'uuid', 'user_id' => 'uuid', 'movie_id' => 'uuid', 'score' => 'numeric (5,4)', 'reason' => 'text',
            'created_at' => 'timestamp without time zone NULL', 'updated_at' => 'timestamp without time zone NULL',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'recommendations'"))->pluck('indexname');
        $this->assertContains('recommendations_user_id_movie_id_unique', $indexes);
        $this->assertContains('recommendations_user_id_score_index', $indexes);
    }

    public function test_a_user_who_likes_one_genre_gets_mostly_that_genre(): void
    {
        foreach (range(1, 15) as $n) {
            $this->card($this->id($n), "Sci-fi $n", ['Science Fiction'], 6.0);
        }
        foreach (range(16, 30) as $n) {
            $this->card($this->id($n), "Comedy $n", ['Comedy'], 6.0);
        }
        $this->profile(['genre_weights' => [self::SCI_FI => 8, self::COMEDY => 1], 'seen_movie_ids' => [self::INCEPTION => null]]);

        $picks = $this->service()->recompute(self::USER);

        $titles = $picks->map(fn ($pick) => $this->cards[$pick->movie_id]['title']);
        $sciFi = $titles->filter(fn ($title) => str_starts_with($title, 'Sci-fi'))->count();
        $this->assertGreaterThanOrEqual(15, $sciFi, 'All 15 sci-fi movies are picked.');
        $this->assertTrue(str_starts_with($titles->first(), 'Sci-fi'), 'The best pick is sci-fi.');
        $this->assertSame('You like Science Fiction', $picks->first()->reason);
    }

    public function test_seen_movies_never_appear_even_when_catalog_sends_them(): void
    {
        $this->card($this->id(1), 'Already watched', ['Science Fiction'], 9.0);
        $this->card($this->id(2), 'New one', ['Science Fiction'], 6.0);
        $this->similar[self::INCEPTION] = [$this->id(1), $this->id(2)];
        $this->profile(['genre_weights' => [self::SCI_FI => 4], 'seen_movie_ids' => [self::INCEPTION => 5, $this->id(1) => null]]);

        $ids = $this->service()->recompute(self::USER)->pluck('movie_id')->all();

        $this->assertNotContains(self::INCEPTION, $ids);
        $this->assertNotContains($this->id(1), $ids);
        $this->assertContains($this->id(2), $ids);
    }

    public function test_a_similar_movie_gets_the_bonus_and_the_because_you_liked_reason(): void
    {
        $this->card($this->id(1), 'Interstellar', ['Science Fiction'], 7.0);
        $this->card($this->id(2), 'Some other sci-fi', ['Science Fiction'], 7.0);
        $this->similar[self::INCEPTION] = [$this->id(1)];
        $this->profile(['genre_weights' => [self::SCI_FI => 2], 'seen_movie_ids' => [self::INCEPTION => 5]]);

        $picks = $this->service()->recompute(self::USER)->keyBy('movie_id');

        $this->assertSame('Because you liked Inception', $picks[$this->id(1)]->reason);
        $this->assertSame('You like Science Fiction', $picks[$this->id(2)]->reason);
        $this->assertGreaterThan($picks[$this->id(2)]->score, $picks[$this->id(1)]->score);
    }

    public function test_the_score_follows_the_formula_and_fits_0_to_1(): void
    {
        // Full genre match (3) + full people match (2) + similar (2) + 10 × 0.5 (5) = 12 = the highest raw score.
        $this->card($this->id(1), 'Perfect', ['Science Fiction'], 10.0);
        $this->similar[self::INCEPTION] = [$this->id(1)];
        $this->people[$this->id(1)] = [self::NOLAN];
        // Only genre: 3 + vote 5.0 × 0.5 = 5.5 → 0.4583.
        $this->card($this->id(2), 'Only genre', ['Science Fiction'], 5.0);
        $this->profile([
            'genre_weights' => [self::SCI_FI => 2], 'people_weights' => [self::NOLAN => 2], 'seen_movie_ids' => [self::INCEPTION => 5],
        ]);

        $picks = $this->service()->recompute(self::USER)->keyBy('movie_id');

        $this->assertSame(1.0, $picks[$this->id(1)]->score);
        $this->assertSame(0.4583, $picks[$this->id(2)]->score);
    }

    public function test_favorite_genres_count_as_a_plus_3(): void
    {
        $this->card($this->id(1), 'A horror movie', ['Horror'], 7.0);
        $this->card($this->id(2), 'A comedy', ['Comedy'], 7.0);
        $this->profile(['genre_weights' => [self::COMEDY => 1], 'favorite_genre_ids' => [self::HORROR]]);

        $picks = $this->service()->recompute(self::USER);

        $this->assertSame($this->id(1), $picks->first()->movie_id, 'Horror (0 + 3) beats comedy (1).');
        $this->assertSame('You like Horror', $picks->first()->reason);
    }

    public function test_only_the_best_20_are_kept_best_first_and_old_rows_are_removed(): void
    {
        foreach (range(1, 25) as $n) {
            $this->card($this->id($n), "Sci-fi $n", ['Science Fiction'], $n / 3);
        }
        $this->profile(['genre_weights' => [self::SCI_FI => 3], 'seen_movie_ids' => [self::INCEPTION => null]]);
        $old = Recommendation::forceCreate(['user_id' => self::USER, 'movie_id' => $this->id(99), 'score' => 0.9, 'reason' => 'Old pick']);

        $picks = $this->service()->recompute(self::USER);

        $this->assertCount(RecommendationService::KEEP, $picks);
        $this->assertSame($this->id(25), $picks->first()->movie_id);
        $this->assertSame($picks->pluck('score')->sortDesc()->values()->all(), $picks->pluck('score')->all());
        $this->assertNull(Recommendation::find($old->id), 'A pick no longer in the top 20 is deleted.');
        $this->assertSame(20, Recommendation::forUser(self::USER)->count());
    }

    public function test_recomputing_updates_the_same_rows(): void
    {
        $this->card($this->id(1), 'Sci-fi', ['Science Fiction'], 7.0);
        $this->profile(['genre_weights' => [self::SCI_FI => 3], 'seen_movie_ids' => [self::INCEPTION => null]]);

        $first = $this->service()->recompute(self::USER)->first();
        $this->cards[$this->id(1)]['vote_average'] = 9.0;
        $second = $this->service()->recompute(self::USER)->first();

        $this->assertSame($first->id, $second->id, 'Upserted on (user_id, movie_id), not added again.');
        $this->assertGreaterThan($first->score, $second->score);
    }

    public function test_a_user_without_a_profile_gets_nothing_and_catalog_is_not_called(): void
    {
        $this->assertSame([], $this->service()->recompute(self::USER)->all());
        Http::assertNothingSent();
    }

    public function test_catalog_down_throws_and_keeps_the_stored_picks(): void
    {
        $this->profile(['genre_weights' => [self::SCI_FI => 3]]);
        Recommendation::forceCreate(['user_id' => self::USER, 'movie_id' => $this->id(1), 'score' => 0.5, 'reason' => 'You like Science Fiction']);
        $this->catalogDown = true;

        try {
            $this->service()->recompute(self::USER);
            $this->fail('No exception with Catalog down.');
        } catch (CatalogUnavailable) {
            $this->assertSame(1, Recommendation::forUser(self::USER)->count());
        }
    }

    public function test_results_are_stale_after_one_hour(): void
    {
        $this->assertTrue($this->service()->isStale(self::USER), 'No results yet.');

        Carbon::setTestNow('2026-11-14T10:00:00Z');
        Recommendation::forceCreate(['user_id' => self::USER, 'movie_id' => $this->id(1), 'score' => 0.5, 'reason' => 'x']);

        Carbon::setTestNow('2026-11-14T10:59:00Z');
        $this->assertFalse($this->service()->isStale(self::USER));

        Carbon::setTestNow('2026-11-14T11:01:00Z');
        $this->assertTrue($this->service()->isStale(self::USER));

        Carbon::setTestNow();
    }
}
