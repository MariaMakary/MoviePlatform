# Notification service

Lumen 10 service on port **8006**. Database: `notification-db` (`notification_db`, host port 5438). Owner: Joe.

User notifications.

## Events

- Publishes: nothing
- Consumes: `user.registered`, `user.blocked`, `user.deleted`, `user.followed`, `movie_night.invite_sent`, `movie_night.invite_answered`, `movie_night.finalized`

Payloads and rules: `docs/events.md`.

## Notes

- Turns consumed events into notifications for users. Event payloads carry the names it needs (e.g. `follower_name`), so it never calls other services to build the text.
- Real time: broadcasts `NotificationCreated` on `user.{id}` through Reverb, using the `pusher` broadcast driver.

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/notification.md` (this service's endpoints),
`docs/database.md` and the ERD in `docs/erd/`.

## Running

`composer run dev` starts `php -S 127.0.0.1:8006 -t public` + `php artisan events:consume`. Tests: `vendor/bin/phpunit`.
