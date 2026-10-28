# Engagement service

Lumen 10 service on port **8003**. Database: `engagement-db` (`engagement_db`, host port 5435). Owner: Tonio.

Ratings, reviews, watchlist, watch history, custom lists and moderation.

## Events

- Publishes: `rating.added`, `review.created`, `review.updated`, `movie.watched`, `list.created`
- Consumes: `user.blocked`, `user.deleted`, `review.analyzed`

Payloads and rules: `docs/events.md`.

## Notes

- One rating per user and movie: changing it publishes `rating.added` again with the new score.
- Review sentiment is not computed here: Intelligence sends it back with `review.analyzed`, stored as `sentiment_label` / `sentiment_score`.

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/engagement.md` (this service's endpoints),
`docs/database.md` and the ERD in `docs/erd/`.

## Running

`composer run dev` starts `php -S 127.0.0.1:8003 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
