# Identity service

Lumen 10 service on port **8001**. Database: `identity-db` (`identity_db`, host port 5433). Owner: Maria.

Users, authentication, social login (Google/GitHub), roles and JWT issuing.

## Events

- Publishes: `user.registered`, `user.genres_updated`, `user.blocked`, `user.unblocked`, `user.deleted`
- Consumes: nothing

Payloads and rules: `docs/events.md`.

## Notes

- Outgoing events are classes in `app/Integration/Events` with the routing key and the payload of `docs/events.md`,
  published after the transaction commits:
  `$this->events->publish(UserRegistered::ROUTING_KEY, UserRegistered::data($user))`. Social login (1149) reuses
  `UserRegistered`.
- `App\Services\AuthService` does register, login, refresh and "current user": always reuse it. On an unknown email it still
  runs `Hash::check` against a fixed dummy hash, so a login takes the same time whether the email exists or not.
  Emails go through `User::normalizeEmail()` (trim + lowercase) when saved, looked up and checked for uniqueness.
- `App\Services\TokenService::issue()` returns an `IssuedToken` (`user`, `token`, `expiresAt`), output with
  `TokenResource` (`{token, expires_at, user}`). It signs RS256 tokens with `secret('jwt_private_key')`. Every service verifies them with the committed `keys/jwt_public.pem`. Never generate a new key pair without telling the team.
- `composer.json` ignores advisory `PKSA-zwc5-qtrz-zm1n` (CVE-2026-48019: Laravel's `email` rule lets line breaks through, which can add mail headers such as Bcc) because Lumen 10 has no patched `illuminate/mail`. We are protected only by `EmailRules::NO_CONTROL_CHARACTERS` on every email field and by mailing `$user->email` from the database, never the request field: never remove either one.
- It is the only service that stores users. Other services only keep `user_id` (a UUID) and read the user from the token.
- Anything about another user goes out through `PublicUserResource` (`id`, `name`, `avatar`, `bio`, `created_at`),
  from `App\Services\UserService`. `UserResource` (with `email` and `role`) is only for the user's own account.
- `App\Services\ProfileService` changes the logged-in user's own profile: name and bio, avatar, password and favorite
  genres. For genres it locks the user row while replacing the `user_genres` rows, so two saves at the same time cannot
  collide, and publishes `UserGenresUpdated` with the full new list after the commit.
- Avatars are files in `public/avatars/<uuid>.<ext>` (gitignored, so they stay on the laptop that runs Identity),
  served by `php -S`; `users.avatar` holds the full URL built from `APP_URL`. `ProfileService` deletes a previous avatar
  only when its URL points to that folder, never an avatar from Google or GitHub. Deleting a user (1271) must delete
  the file the same way. Uploads are checked by content (`mimes:jpg,png`); in tests build a real
  `new UploadedFile($tempPath, $name, null, null, true)`, because `UploadedFile::fake()` reports its type from the file
  name.
- `Database\Seeders\AdminSeeder` (`php artisan db:seed --class=AdminSeeder`) creates `admin@kaia.com` with role admin
  from `config/admin.php`; the password is `secret('admin_password')`, at least 8 characters, or the seeder stops. If
  the email already exists it changes nothing, so it never turns someone else's account into an admin.
- Social login uses Laravel Socialite in **stateless** mode (Lumen has no sessions).

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/identity.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/identity-erd.drawio`.

## Running

`composer run dev` runs `composer install` and `php artisan migrate --force`, then starts `php -S 127.0.0.1:8001 -t public` and the `mail` worker (`php artisan queue:work --tries=3 --backoff=30`). Identity publishes events but consumes none. Tests: `vendor/bin/phpunit`.

Emails are queued, never sent during the request: `Mail::to(...)->queue(...)` puts them in the `jobs` table
(`QUEUE_CONNECTION=database`) and the worker sends them. Sending through a real SMTP server takes several seconds,
longer than the Gateway's 3 s timeout, and the time would also show which emails have an account. A failed email is
retried 3 times, then kept in `failed_jobs` (`php artisan queue:failed`, `queue:retry`). The queued job holds the reset
link, so `php artisan queue:flush` clears failed ones. Lumen does not bind the queue or the mail factory by itself:
`bootstrap/app.php` registers `QueueServiceProvider` and aliases `mail.manager` to `Illuminate\Contracts\Mail\Factory`.
A queued Mailable cannot keep closures, so `ResetPasswordMail` adds its plain-text part in `build()`, which runs in the
worker. With `QUEUE_CONNECTION=sync` (the tests) the email is sent at once.
The tests use the real `identity-db` (CHECK constraint, unique email), so run `docker compose up -d` and
`composer run dev` once first (it applies the migrations). They use `DatabaseTransactions`, so they leave no rows behind.

## Models

`User` (UUID, `HasUuids`) with `UserFactory` (`->admin()`, `->blocked()`). `password` is hashed by its cast, so assign
the plain value; it is `null` for social-login accounts and hidden from `toArray()`. `role` is cast to the
`MoviePlatform\Common\Auth\Role` enum (`Role::User`, `Role::Admin`). `role` and `is_blocked` are not fillable: a
request can never set them, so admin actions set them explicitly (`$user->role = Role::Admin`).

`UserGenre` is a row of `user_genres` (primary key `user_id` + `genre_id`, only `created_at`), read through
`$user->genres` (sorted by `genre_id`). `UserResource` adds `genre_ids` only when `genres` is loaded
(`GET /api/me`, `PUT /api/me/genres`).
