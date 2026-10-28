# Social service

Lumen 10 service on port **8004**. Database: `social-db` (`social_db`, host port 5436). Owner: Maria (feed, movie nights) · Joe (follows, profiles, privacy, chat).

Follows, profiles, privacy, the activity feed, movie nights and chat rooms.

## Events

- Publishes: `user.followed`, `movie_night.invite_sent`, `movie_night.invite_answered`, `movie_night.finalized`
- Consumes: `user.blocked`, `user.unblocked`, `user.deleted`, `movie.deleted`, `rating.added`, `review.created`, `review.updated`, `movie.watched`, `list.created`

Payloads and rules: `docs/events.md`.

## Notes

- Real time: broadcasts `MessageSent` on `room.{id}` and `VotesUpdated` on `movie-night.{id}` through Reverb (which runs from the Gateway), using the `pusher` broadcast driver.
- The feed is built from consumed events; it never reads Engagement's database.

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/social.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/social-erd.drawio`.

## Running

`composer run dev` starts `php -S 127.0.0.1:8004 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
