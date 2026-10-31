# Intelligence service

Lumen 10 service on port **8005**. Database: `intelligence-db` (`intelligence_db`, host port 5437). Owner: Joe.

Recommendations, review sentiment, weather and calendar.

## Events

- Publishes: `review.analyzed`
- Consumes: `user.genres_updated`, `user.deleted`, `movie.deleted`, `rating.added`, `review.created`, `review.updated`, `movie.watched`

Payloads and rules: `docs/events.md`.

## Notes

- External APIs only through Clients in `app/Integration/Clients`: Hugging Face (`secret('hf_api_token')`) for sentiment, OpenWeatherMap (`secret('openweather_api_key')`) for weather, cached in Redis.
- Movie details come from Catalog's `/api/internal/*` routes with `X-Service-Token`, never from Catalog's database.

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/intelligence.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/intelligence-erd.drawio`.

## Running

`composer run dev` runs `composer install` and `php artisan migrate --force`, then starts `php -S 127.0.0.1:8005 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
