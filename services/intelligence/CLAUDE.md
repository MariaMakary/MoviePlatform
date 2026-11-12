# Intelligence service

Lumen 10 service on port **8005**. Database: `intelligence-db` (`intelligence_db`, host port 5437). Owner: Joe.

Recommendations, review sentiment, weather and calendar.

## Events

- Publishes: `review.analyzed`
- Consumes: `user.genres_updated`, `user.deleted`, `movie.deleted`, `rating.added`, `review.created`, `review.updated`, `movie.watched`

Payloads and rules: `docs/events.md`.

## Notes

- External APIs only through Clients in `app/Integration/Clients`: Azure AI Language (`secret('azure_language_key')`) for sentiment, Open-Meteo and OpenStreetMap Nominatim for weather (no key), cached in Redis.
- `WeatherClient::current($lat, $lon)` returns `['icon_code' => int, 'phrase' => string, 'temp' => float, 'city' => ?string]`.
  The weather comes from Open-Meteo (`/v1/forecast?current=temperature_2m,weather_code`, °C): `icon_code` is the WMO
  weather code and `phrase` its English text from the `WeatherCondition` enum. Open-Meteo has no city name, so the city
  comes from Nominatim reverse geocoding (`city`, else `town`, `village` or `municipality`, in English; `null` when
  there is none). Coordinates are rounded to 2 decimals (about 1 km): the weather is cached in Redis for 30 minutes
  and the city for 7 days under those rounded coordinates, so nearby users share one call. Nominatim allows one
  request a second and asks for an identifying `User-Agent`, which `config/services.php` sets. Any failure (timeout,
  4xx/429, 5xx, unexpected answer or unknown weather code) throws `WeatherUnavailable`, an `HttpException(503)`, and
  is not cached. Both APIs are free with no key or billing account. Azure Maps was tried first, but Azure for Students
  subscriptions only allow a few regions and none of them offers Azure Maps; OpenWeatherMap was dropped to avoid
  another key. Redis comes from `illuminate/redis` + `predis/predis` (`CACHE_DRIVER=redis` in `.env`); tests use the
  `array` cache (`phpunit.xml`).
- `SentimentClient::analyze($text)` returns `['label' => SentimentLabel, 'score' => float]`: the highest of Azure's three
  confidence scores, so Azure's `mixed` answer never reaches us. The team shares one free F0 resource
  (`kaia-language`, UAE North): 5,000 texts a month, one text = up to 1,000 characters, so reviews are cut at 1,000
  characters (`MAX_CHARACTERS`) and analyzed as English. Past the limit Azure refuses calls (403/429) instead of
  charging. Any failure (timeout, bad key, limit, refused document) throws `SentimentUnavailable`, so the event is
  retried and then dead-lettered; the log never holds the key or the review text. Hugging Face was dropped because its
  free accounts no longer get monthly credits. `SentimentUnavailable` is an `HttpException(503)`: in the consumer it
  makes the event retry, in `POST /api/sentiment/analyze` (admin only) it answers 503.
- `AnalyzeReviewHandler` handles both `ReviewCreated` and `ReviewUpdated` (both carry the current text) through
  `SentimentService::analyzeReview()`: one `sentiment_results` row per review (`updateOrCreate` on the unique
  `review_id`, Engagement's `reviews.id`, no foreign key), so an edit replaces the result. The handler runs inside the
  consumer's transaction, so `ReviewAnalyzed` is published with `DB::afterCommit`. In tests the test's own transaction
  never commits, so `SentimentTest` swaps in a transaction manager that treats getting back to level 1 as the commit.
- Movie details come from Catalog's `/api/internal/*` routes with `X-Service-Token`, never from Catalog's database.

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/intelligence.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/intelligence-erd.drawio`.

## Running

`composer run dev` runs `composer install` and `php artisan migrate --force`, then starts `php -S 127.0.0.1:8005 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
