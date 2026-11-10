<?php

namespace Tests;

use App\Enums\CreditRole;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class CatalogSchemaTest extends TestCase
{
    use DatabaseTransactions;

    private function assertRefused(callable $query, string $sqlState): void
    {
        try {
            DB::transaction($query);
            $this->fail("The database accepted a row it should refuse (expected SQLSTATE $sqlState).");
        } catch (QueryException $e) {
            $this->assertSame($sqlState, $e->getCode());
        }
    }

    private function columns(string $table): array
    {
        return collect(DB::select(
            'select column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable, column_default
             from information_schema.columns where table_schema = current_schema() and table_name = ? order by ordinal_position',
            [$table]
        ))->mapWithKeys(fn ($c) => [$c->column_name => trim(implode(' ', [
            $c->data_type,
            $c->character_maximum_length ? "({$c->character_maximum_length})" : '',
            $c->data_type === 'numeric' ? "({$c->numeric_precision},{$c->numeric_scale})" : '',
            $c->is_nullable === 'YES' ? 'NULL' : '',
            $c->column_default !== null ? 'default '.trim(Str::before($c->column_default, '::'), "'") : '',
        ]))])->map(fn ($type) => preg_replace('/\s+/', ' ', $type))->all();
    }

    public function test_movies_columns_match_the_erd(): void
    {
        $this->assertSame([
            'id' => 'uuid',
            'tmdb_id' => 'integer NULL',
            'title' => 'character varying (255)',
            'overview' => 'text NULL',
            'poster_path' => 'character varying (255) NULL',
            'backdrop_path' => 'character varying (255) NULL',
            'release_date' => 'date NULL',
            'runtime' => 'smallint NULL',
            'vote_average' => 'numeric (3,1) NULL',
            'popularity' => 'double precision default 0',
            'trailer_key' => 'character varying (32) NULL',
            'is_edited' => 'boolean default false',
            'synced_at' => 'timestamp without time zone NULL',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $this->columns('movies'));
    }

    public function test_genres_people_and_link_tables_match_the_erd(): void
    {
        $this->assertSame([
            'id' => 'uuid',
            'tmdb_id' => 'integer NULL',
            'name' => 'character varying (100)',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $this->columns('genres'));

        $this->assertSame([
            'id' => 'uuid',
            'tmdb_id' => 'integer NULL',
            'name' => 'character varying (255)',
            'profile_path' => 'character varying (255) NULL',
            'biography' => 'text NULL',
            'birthday' => 'date NULL',
            'known_for' => 'character varying (50) NULL',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $this->columns('people'));

        $this->assertSame(['movie_id' => 'uuid', 'genre_id' => 'uuid'], $this->columns('movie_genre'));

        $this->assertSame([
            'id' => 'uuid',
            'movie_id' => 'uuid',
            'person_id' => 'uuid',
            'role' => 'character varying (20)',
            'character' => 'character varying (255) NULL',
            'cast_order' => 'smallint NULL',
        ], $this->columns('movie_person'));
    }

    public function test_the_erd_indexes_exist(): void
    {
        $indexes = collect(DB::select("select indexname from pg_indexes where schemaname = current_schema()"))->pluck('indexname');

        foreach ([
            'movies_tmdb_id_unique', 'people_tmdb_id_unique', 'genres_tmdb_id_unique', 'genres_name_unique',
            'movies_title_fulltext', 'movies_release_date_index', 'movies_popularity_index', 'movies_vote_average_index',
            'movie_person_movie_id_role_cast_order_index', 'movie_person_person_id_index', 'movie_genre_genre_id_index',
            'movie_person_movie_id_person_id_role_unique',
        ] as $index) {
            $this->assertContains($index, $indexes, "Missing index $index.");
        }
    }

    public function test_movie_genres_and_genre_movies(): void
    {
        $movie = Movie::factory()->create();
        [$drama, $crime] = Genre::factory()->count(2)->create();

        $movie->genres()->attach([$drama->id, $crime->id]);

        $this->assertEqualsCanonicalizing([$drama->id, $crime->id], $movie->genres()->pluck('genres.id')->all());
        $this->assertSame([$movie->id], $drama->movies()->pluck('movies.id')->all());
    }

    public function test_cast_is_ordered_by_cast_order_with_character_and_directors_are_separate(): void
    {
        $movie = Movie::factory()->create();
        [$second, $first, $director] = Person::factory()->count(3)->create();

        $movie->cast()->attach($second->id, ['character' => 'Arthur', 'cast_order' => 1]);
        $movie->cast()->attach($first->id, ['character' => 'Cobb', 'cast_order' => 0]);
        $movie->directors()->attach($director->id);

        $cast = $movie->cast()->get();
        $this->assertSame([$first->id, $second->id], $cast->pluck('id')->all());
        $this->assertSame('Cobb', $cast[0]->pivot->character);
        $this->assertSame(0, $cast[0]->pivot->cast_order);
        $this->assertTrue(Str::isUuid($cast[0]->pivot->id));
        $this->assertSame(CreditRole::Cast, $movie->cast()->first()->pivot->role);

        $this->assertSame([$director->id], $movie->directors()->pluck('people.id')->all());
        $this->assertSame(CreditRole::Director, $movie->directors()->first()->pivot->role);
        $this->assertSame(CreditRole::Director->value, DB::table('movie_person')->where('person_id', $director->id)->value('role'));
    }

    public function test_person_movies_include_the_role(): void
    {
        [$inception, $tenet] = Movie::factory()->count(2)->create();
        $nolan = Person::factory()->create();

        $inception->directors()->attach($nolan->id);
        $tenet->cast()->attach($nolan->id, ['character' => 'Cameo', 'cast_order' => 9]);

        $roles = $nolan->movies()->get()->mapWithKeys(fn ($movie) => [$movie->id => $movie->pivot->role])->all();

        $this->assertEquals([$inception->id => CreditRole::Director, $tenet->id => CreditRole::Cast], $roles);
        $this->assertContainsOnlyInstancesOf(CreditRole::class, $roles);
    }

    public function test_the_same_person_can_act_in_and_direct_a_movie(): void
    {
        $movie = Movie::factory()->create();
        $person = Person::factory()->create();

        $movie->cast()->attach($person->id, ['character' => 'Himself', 'cast_order' => 0]);
        $movie->directors()->attach($person->id);

        $this->assertSame(2, DB::table('movie_person')->where('movie_id', $movie->id)->count());
    }

    public function test_a_duplicate_credit_is_refused(): void
    {
        $movie = Movie::factory()->create();
        $person = Person::factory()->create();
        $movie->cast()->attach($person->id, ['character' => 'Cobb', 'cast_order' => 0]);

        $this->assertRefused(fn () => $movie->cast()->attach($person->id, ['character' => 'Cobb again', 'cast_order' => 1]), '23505');
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $movie = Movie::factory()->create();
        $person = Person::factory()->create();

        $this->assertRefused(fn () => DB::table('movie_person')->insert([
            'id' => (string) Str::orderedUuid(), 'movie_id' => $movie->id, 'person_id' => $person->id, 'role' => 'producer',
        ]), '23514');
    }

    public function test_deleting_a_movie_removes_its_genre_links_and_credits_only(): void
    {
        $movie = Movie::factory()->create();
        $genre = Genre::factory()->create();
        $person = Person::factory()->create();
        $movie->genres()->attach($genre->id);
        $movie->cast()->attach($person->id, ['cast_order' => 0]);

        $movie->delete();

        $this->assertSame(0, DB::table('movie_genre')->where('movie_id', $movie->id)->count());
        $this->assertSame(0, DB::table('movie_person')->where('movie_id', $movie->id)->count());
        $this->assertNotNull(Genre::find($genre->id));
        $this->assertNotNull(Person::find($person->id));
    }

    public function test_tmdb_id_is_unique_but_local_rows_can_all_be_null(): void
    {
        [$first, $second] = Movie::factory()->local()->count(2)->create();
        $this->assertNull($first->fresh()->tmdb_id);
        $this->assertNull($second->fresh()->tmdb_id);

        Movie::factory()->create(['tmdb_id' => 900000777]);
        $this->assertRefused(fn () => Movie::factory()->create(['tmdb_id' => 900000777]), '23505');
    }

    public function test_database_defaults_for_a_movie_created_without_them(): void
    {
        $id = (string) Str::orderedUuid();
        DB::table('movies')->insert(['id' => $id, 'title' => 'Local movie']);

        $movie = Movie::find($id);
        $this->assertSame(0.0, $movie->popularity);
        $this->assertFalse($movie->is_edited);
        $this->assertNull($movie->tmdb_id);
    }
}
