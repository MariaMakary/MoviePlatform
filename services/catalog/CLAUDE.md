# Catalog service

Lumen 10 service on port **8002**. Database: `catalog-db` (`catalog_db`, host port 5434). Owner: Joy (search: Tonio · genres/people/caching: Maria · internal endpoints: Joe).

TMDB integration, the local movie database, search, genres and people.

## Events

- Publishes: `movie.updated`, `movie.deleted`
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

`composer run dev` starts `php -S 127.0.0.1:8002 -t public` (it only publishes events). Tests: `vendor/bin/phpunit`.
