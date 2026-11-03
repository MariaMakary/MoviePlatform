# Social service

Lumen 10 service on port **8004**. Database: `social-db` (`social_db`, host port 5436). Owner: Joe · Maria.

Follows, profiles, privacy, the activity feed, movie nights and chat rooms.

## Events

- Publishes: `user.followed`, `movie_night.invite_sent`, `movie_night.invite_answered`, `movie_night.finalized`
- Consumes: `user.blocked`, `user.unblocked`, `user.deleted`, `movie.deleted`, `rating.added`, `review.created`, `review.updated`, `movie.watched`, `list.created`

Payloads and rules: `docs/events.md`.

## Notes

- Real time: broadcasts `MessageSent` on `room.{id}` and `VotesUpdated` on `movie-night.{id}` through Reverb (which runs from the Gateway), using the `pusher` broadcast driver.
- The feed is built from consumed events; it never reads Engagement's database.

## Models

- `MovieNight` (UUID, `HasUuids`, `MovieNightFactory`): `status` is the `App\Enums\MovieNightStatus` enum (`Open`,
  `Finalized`, `Cancelled`), `open` by default and not fillable, so only the service changes it. `host_id` and
  `winner_movie_id` are plain ids from Identity and Catalog. Relations: `participants`, `candidates`, `votes`.
- `Participant`: primary key (`movie_night_id`, `user_id`), so a user is invited once per night; only `created_at`.
  `status` is `App\Enums\ParticipantStatus` (`Pending`, `Accepted`, `Declined`), `pending` by default; `responded_at`
  is set when the user answers. Change rows with a query (`Participant::where(...)->update(...)`), like `Follow`.
- `Candidate` (UUID): UNIQUE (`movie_night_id`, `movie_id`), so a movie is proposed once per night; `added_by` is the
  user who proposed it; only `created_at`. Relation: `votes`.
- `Vote`: primary key (`movie_night_id`, `user_id`), so one vote per user per night; changing a vote updates
  `candidate_id` with a query. The database does not check that the candidate belongs to the same night: the voting
  service (1226) must check it.
- Deleting a night deletes its participants, candidates and votes; deleting a candidate deletes its votes (cascade
  foreign keys, all inside Social).

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/social.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/social-erd.drawio`.

## Running

`composer run dev` runs `composer install` and `php artisan migrate --force`, then starts `php -S 127.0.0.1:8004 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
