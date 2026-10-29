# Identity service

Lumen 10 service on port **8001**. Database: `identity-db` (`identity_db`, host port 5433). Owner: Maria.

Users, authentication, social login (Google/GitHub), roles and JWT issuing.

## Events

- Publishes: `user.registered`, `user.genres_updated`, `user.blocked`, `user.unblocked`, `user.deleted`
- Consumes: nothing

Payloads and rules: `docs/events.md`.

## Notes

- `App\Services\TokenService` signs RS256 tokens with `secret('jwt_private_key')`. Every service verifies them with the committed `keys/jwt_public.pem`. Never generate a new key pair without telling the team.
- It is the only service that stores users. Other services only keep `user_id` (a UUID) and read the user from the token.
- Social login uses Laravel Socialite in **stateless** mode (Lumen has no sessions).

## Before coding

Read `docs/lumen.md` (how to write Lumen code here), `docs/api/identity.md` (this service's endpoints),
`docs/database.md` and its ERD in `docs/erd/identity-erd.drawio`.

## Running

`composer run dev` starts `php -S 127.0.0.1:8001 -t public` (it only publishes events). Tests: `vendor/bin/phpunit`.
