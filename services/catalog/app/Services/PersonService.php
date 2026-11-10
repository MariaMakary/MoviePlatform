<?php

namespace App\Services;

use App\Integration\Clients\TmdbClient;
use App\Integration\Clients\TmdbUnavailable;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PersonService
{
    public const RESYNC_AFTER_DAYS = 7;

    public function __construct(private TmdbClient $tmdb, private MovieService $movies) {}

    /**
     * A person with their filmography. A TMDB person is synced (details and movie credits) on first open, then at
     * most once every RESYNC_AFTER_DAYS: the people table has no synced_at, so the cache remembers it. If TMDB
     * fails, the local data is served and the sync is tried again on the next open.
     */
    public function find(string $id): Person
    {
        $person = Person::findOrFail($id);

        if ($person->tmdb_id !== null && ! Cache::has($this->syncedKey($person))) {
            try {
                $this->sync($person);
                Cache::put($this->syncedKey($person), true, Carbon::now()->addDays(self::RESYNC_AFTER_DAYS));
            } catch (TmdbUnavailable) {
                Log::info('Serving the local copy of a person, TMDB is unavailable', ['person_id' => $person->id]);
            }
        }

        return $person->load('movies.genres');
    }

    private function sync(Person $person): void
    {
        $data = $this->tmdb->person($person->tmdb_id);

        // No longer on TMDB: keep the local copy.
        if ($data === null) {
            return;
        }

        // Missing movies are created from the credit data, like the popular and upcoming lists do.
        $movies = $this->movies->ensureLocal($data['movies'])->keyBy('tmdb_id');

        DB::transaction(function () use ($person, $data, $movies) {
            $person->fill($data['person'])->save();

            $rows = collect($data['movies'])
                ->filter(fn (array $credit) => $movies->has($credit['movie']['tmdb_id']))
                ->map(fn (array $credit) => [
                    'id' => (string) Str::orderedUuid(),
                    'movie_id' => $movies->get($credit['movie']['tmdb_id'])->id,
                    'person_id' => $person->id,
                    'role' => $credit['role']->value,
                    'character' => $credit['character'],
                    'cast_order' => null,
                ])
                ->values();

            // A credit already saved by a movie sync keeps its cast_order.
            if ($rows->isNotEmpty()) {
                DB::table('movie_person')->upsert($rows->all(), ['movie_id', 'person_id', 'role'], ['character']);
            }
        });
    }

    private function syncedKey(Person $person): string
    {
        return "catalog:person-synced:{$person->id}";
    }
}
