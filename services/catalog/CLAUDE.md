# Catalog service

Lumen 10 service on port **8002**. Database: `catalog-db` (`catalog_db`, host port 5434). Owner: Joy.

TMDB integration, the local movie database, search, genres and people.

## Events

- Publishes: `movie.deleted`
- Consumes: nothing

Payloads and rules: `docs/events.md`.

## Notes

- TMDB is called only from a Client in `app/Integration/Clients`, with the key from `secret('tmdb_api_key')`. TMDB responses are cached in Redis.
- Every other service references a movie by Catalog's own `movies.id` (UUID), never by `tmdb_id`.
- Other services read movie data through `/api/internal/*` routes protected by the `service` middleware (`X-Service-Token`).

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/catalog.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/catalog-erd.drawio`.

## Running

`composer run dev` runs `composer install` and `php artisan migrate --force`, then starts `php -S 127.0.0.1:8002 -t public` (it only publishes events). Tests: `vendor/bin/phpunit`.
The tests use the real `catalog-db` (CHECK constraints, full-text index, cascades), so run `docker compose up -d` and
`composer run dev` once first (it applies the migrations). They use `DatabaseTransactions`, so they leave no rows behind.

## TMDB

`App\Integration\Clients\TmdbClient` is the only code that calls TMDB (`en-US`, 5-second timeout). The key comes from
`secret('tmdb_api_key')` in `config/services.php`: either the v3 "API Key" (sent as `api_key`) or the "API Read Access
Token" (starts with `eyJ`, sent as `Authorization: Bearer`).

| Method | Returns (our column names, ready to save) |
|---|---|
| `popular($page)`, `upcoming($page)`, `search($query, $page)`, `discover($genreTmdbId, $year, $page)` | `['results' => [['movie' => […], 'genre_tmdb_ids' => […]]], 'page', 'total_pages']` |
| `movie($tmdbId)` | `['movie' => […incl. runtime, trailer_key], 'genres' => [['tmdb_id', 'name']], 'cast' => [['person' => […], 'character', 'cast_order']] (top 20), 'directors' => [['person' => […]]]]`, or `null` if TMDB has no such movie |
| `person($tmdbId)` | `['person' => […incl. biography, birthday], 'movies' => [['movie' => […], 'genre_tmdb_ids', 'role' (CreditRole), 'character']]]` (movies only, cast and director), or `null` |
| `genres()` | `[['tmdb_id', 'name']]` |

Any other TMDB error or timeout throws `TmdbUnavailable`, which the API returns as `503 {"message": "TMDB is
unavailable."}`. The key never reaches a log or a response: errors are logged with the path and status only.

## Filling the database from TMDB

Each laptop has its own `catalog-db`, so run this once (needs your key in `secrets/tmdb_api_key`):

```powershell
php artisan catalog:sync-genres          # all TMDB genres (19)
php artisan catalog:sync-popular 5       # 5 pages × 20 = 100 popular movies, with cast, directors and trailer
```

Both are safe to run again: they update instead of duplicating. `App\Services\MovieSyncService::sync($tmdbId)`
upserts one movie by `tmdb_id`: its fields, genres, top 15 cast, director(s), trailer key and `synced_at`. A movie with
`is_edited = true` keeps its fields and genres (only `synced_at` and the credits change). Credits are upserted on
UNIQUE (`movie_id`, `person_id`, `role`); a credit that left TMDB's top 15 is removed. If TMDB is down, the commands
print "TMDB is unavailable." and exit with code 1; movies saved before the error are kept.

## Models

`Movie`, `Genre`, `Person` (table `people`) with factories in `database/factories`. Credits live in `movie_person`
through the `Credit` pivot (its own UUID `id`): use `$movie->cast()` (ordered by `cast_order`, with `character`) and
`$movie->directors()`. They set `role` on attach, so `$movie->cast()->attach($personId, ['character' => …,
'cast_order' => 0])` is all you need.

A credit's role is the enum `App\Enums\CreditRole` (`Cast = 'cast'`, `Director = 'director'`), cast on the `Credit`
pivot: `$movie->cast()->first()->pivot->role` and the pivot of `$person->movies()` are `CreditRole` cases, so compare
with `=== CreditRole::Director`. Never write `'cast'` or `'director'` in code: use the case, and `->value` only where
the model is bypassed (`DB::table('movie_person')`, string keys). The `movie_person` column stays `varchar` with its
CHECK (`docs/database.md`).
