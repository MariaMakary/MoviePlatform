# Intelligence service

Lumen 10 service on port **8005**. Database: `intelligence-db` (`intelligence_db`, host port 5437). Owner: Joe.

Recommendations, review sentiment, weather and calendar.

## Events

- Publishes: `review.analyzed`
- Consumes: `user.genres_updated`, `user.deleted`, `movie.deleted`, `rating.added`, `review.created`, `review.updated`, `movie.watched`

Payloads and rules: `docs/events.md`.

## Notes

- External APIs only through Clients in `app/Integration/Clients`: Azure AI Language (`secret('azure_language_key')`) for sentiment, OpenWeatherMap (`secret('openweather_api_key')`) for weather, cached in Redis.
- `SentimentClient::analyze($text)` returns `['label' => SentimentLabel, 'score' => float]`: the highest of Azure's three
  confidence scores, so Azure's `mixed` answer never reaches us. The team shares one free F0 resource
  (`kaia-language`, UAE North): 5,000 texts a month, one text = up to 1,000 characters, so reviews are cut at 1,000
  characters (`MAX_CHARACTERS`) and analyzed as English. Past the limit Azure refuses calls (403/429) instead of
  charging. Any failure (timeout, bad key, limit, refused document) throws `SentimentUnavailable`, so the event is
  retried and then dead-lettered; the log never holds the key or the review text. Hugging Face was dropped because its
  free accounts no longer get monthly credits.
- Movie details come from Catalog's `/api/internal/*` routes with `X-Service-Token`, never from Catalog's database.

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/intelligence.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/intelligence-erd.drawio`.

## Running

`composer run dev` runs `composer install` and `php artisan migrate --force`, then starts `php -S 127.0.0.1:8005 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
