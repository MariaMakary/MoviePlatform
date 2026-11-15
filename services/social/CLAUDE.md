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

## Movie nights

- `App\Services\MovieNightService` creates nights (the host becomes an `accepted` participant in the same
  transaction), lists the caller's nights and finds one. Visibility is `MovieNight::withParticipant($userId)`: a user
  who is not a participant gets `404`, never `403`, so nobody can tell a night exists. Reuse it in invites, RSVP and
  voting.
- `App\Services\ParticipantService` changes who takes part: invites and RSVP. It locks the night row
  (`lockForUpdate`) for the whole change, so two requests on the same night at the same time cannot collide, and
  publishes its events after the commit. Order of checks: not a participant → `404`, then the role check (invite: not
  the host → `403`; RSVP: the host → `422`), then night not `open` → `422` (`abort(422, '…')`, no field). An RSVP with
  the same answer as before changes nothing and publishes nothing.
- `MovieNightService::lock($id, $userId)` (the night locked for the transaction, `404` for non-participants) and
  `ensureOpen($night)` (`422` unless `open`) are shared by `ParticipantService`, `VotingService` and the night's own
  `finalize` and `cancel`. Every change on a night uses them, so a finalized or cancelled night refuses them all.
- `MovieNightService::finalize` (host only) picks the candidate with the most votes; on a tie the host's
  `candidate_id` must be one of the tied ones. It gets the winner's title from Catalog through
  `App\Integration\Clients\CatalogClient` (`InternalClient`, `GET /api/internal/movies`) inside the transaction, so
  Catalog down (`503`) or a movie Catalog no longer has (`422`) rolls everything back. `MovieNightFinalized` is
  published after the commit. `cancel` (host only) only sets `cancelled`, with no event.
- `App\Services\VotingService` adds and removes candidates and votes, under the same night lock. Adding and voting
  need an `accepted` participant (`403` otherwise); removing needs who added the movie or the host. After the
  transaction it broadcasts `App\Events\VotesUpdated` (`ShouldBroadcastNow`: Social has no queue worker) with the
  totals of every candidate. A broadcast error is caught and logged as a warning, so Reverb being down never fails
  the request.
- `my_status` (the caller's participant status) and, in the detail, `my_vote` (the caller's candidate id) are added
  with subqueries, so the list needs no extra query per night.
- Tests run with `BROADCAST_DRIVER=null` (`phpunit.xml`) and `Event::fake([VotesUpdated::class])`, so they never
  call Reverb.
- The `{id}` route parameter is checked with the `$uuid` pattern in `routes/api.php`: a non-UUID is a `404`.

## Chat rooms

- `App\Services\RoomService`: one public room per movie. `forMovie` returns the room, or asks Catalog for the title
  (`CatalogClient::movieTitle`) and creates it with `createOrFirst`, so two first visits at the same time still give
  one room (`movie_id` UNIQUE). Catalog is not called for a room that exists.
- Messages use a cursor, not pages: `messages($roomId, $before)` takes the 50 messages before `(created_at, id)` of
  the `before` message (row comparison in Postgres, so messages in the same second are not skipped), newest first,
  then reverses them so the answer is oldest first. It fetches 51 to know `has_more`.
- `send` saves the message in a transaction, then broadcasts `App\Events\MessageSent` (`ShouldBroadcastNow`) with the
  `name` claim as `user_name`. A broadcast error is caught and logged, like `VotesUpdated`. Tests fake it with
  `Event::fake([MessageSent::class])`.

## Models

- `MovieNight` (UUID, `HasUuids`, `MovieNightFactory`): `status` is the `MoviePlatform\Common\MovieNights\MovieNightStatus` enum (`Open`,
  `Finalized`, `Cancelled`), `open` by default and not fillable, so only the service changes it. `host_id` and
  `winner_movie_id` are plain ids from Identity and Catalog. Relations: `participants`, `candidates`, `votes`.
- `Participant`: primary key (`movie_night_id`, `user_id`), so a user is invited once per night; only `created_at`.
  `status` is `MoviePlatform\Common\MovieNights\ParticipantStatus` (`Pending`, `Accepted`, `Declined`, shared with the
  Gateway), `pending` by default; `responded_at`
  is set when the user answers. Change rows with a query (`Participant::where(...)->update(...)`), like `Follow`.
- `Candidate` (UUID): UNIQUE (`movie_night_id`, `movie_id`), so a movie is proposed once per night; `added_by` is the
  user who proposed it; only `created_at`. Relation: `votes`.
- `Vote`: primary key (`movie_night_id`, `user_id`), so one vote per user per night; changing a vote updates
  `candidate_id` with a query. The database does not check that the candidate belongs to the same night:
  `VotingService::vote` checks it (`422`).
- `Room` (UUID): `movie_id` UNIQUE, `name` max 150 (the Catalog title, cut to 150); only `created_at`.
  `last_message_at` is added with a subquery in `RoomService`. `Message` (UUID): `room_id`, `user_id`, `body` max
  1000; only `created_at`; index (`room_id`, `created_at`). Deleting a room deletes its messages.
- Deleting a night deletes its participants, candidates and votes; deleting a candidate deletes its votes (cascade
  foreign keys, all inside Social).

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/social.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/social-erd.drawio`.

## Running

`composer run dev` runs `composer install` and `php artisan migrate --force`, then starts `php -S 127.0.0.1:8004 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
