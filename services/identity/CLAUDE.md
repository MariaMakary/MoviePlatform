# Identity service

Lumen 10 service on port **8001**. Database: `identity-db` (`identity_db`, host port 5433). Owner: Maria.

Users, authentication, social login (Google/GitHub), roles and JWT issuing.

## Events

- Publishes: `user.registered`, `user.genres_updated`, `user.blocked`, `user.unblocked`, `user.deleted`
- Consumes: nothing

Payloads and rules: `docs/events.md`.

## Notes

- `App\Services\AuthService` does login, refresh and "current user": always reuse it. On an unknown email it still
  runs `Hash::check` against a fixed dummy hash, so a login takes the same time whether the email exists or not.
  Emails are lowercased by the `User` model when saved, and by `AuthService` when looked up.
- `App\Services\TokenService::issue()` returns an `IssuedToken` (`user`, `token`, `expiresAt`), output with
  `TokenResource` (`{token, expires_at, user}`). It signs RS256 tokens with `secret('jwt_private_key')`. Every service verifies them with the committed `keys/jwt_public.pem`. Never generate a new key pair without telling the team.
- It is the only service that stores users. Other services only keep `user_id` (a UUID) and read the user from the token.
- Social login uses Laravel Socialite in **stateless** mode (Lumen has no sessions).

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/identity.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/identity-erd.drawio`.

## Running

`composer run dev` starts `php -S 127.0.0.1:8001 -t public` (it only publishes events). Tests: `vendor/bin/phpunit`.
The tests use the real `identity-db` (CHECK constraint, unique email), so run `docker compose up -d` and
`php artisan migrate` first. They use `DatabaseTransactions`, so they leave no rows behind.

## Models

`User` (UUID, `HasUuids`) with `UserFactory` (`->admin()`, `->blocked()`). `password` is hashed by its cast, so assign
the plain value; it is `null` for social-login accounts and hidden from `toArray()`. `role` is cast to the
`MoviePlatform\Common\Auth\Role` enum (`Role::User`, `Role::Admin`). `role` and `is_blocked` are not fillable: a
request can never set them, so admin actions set them explicitly (`$user->role = Role::Admin`).
