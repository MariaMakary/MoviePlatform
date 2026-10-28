# movieplatform/common

Technical code shared by the Gateway (Laravel 12) and the 6 services (Lumen 10). Business logic, models and database
access never go here (CLAUDE.md, golden rule 9).

## How a project loads it

Each project requires it as a local Composer package:

```json
"repositories": [{ "type": "path", "url": "../../packages/common" }],
"require": { "movieplatform/common": "^1.0" }
```

(The Gateway uses `../packages/common`.) Composer links the folder into `vendor/movieplatform/common`, so changes here
are picked up by every project without copying files.

- **Lumen services** register the provider in `bootstrap/app.php`:
  `$app->register(MoviePlatform\Common\CommonServiceProvider::class);`
- **The Gateway** (Laravel) finds it automatically through package discovery.

## Contents

| | |
|---|---|
| `src/secret.php` | `secret('name', $default)`: trimmed content of `<SECRETS_DIR>/<name>` (default `/run/secrets`, relative paths resolved from the project folder), or `env('NAME')` when the file is missing or empty. Only call it from `config/*.php`. |
| `src/CommonServiceProvider.php` | Registers the `jwt` guard and the `jwt`, `admin` and `service` route middleware, in Lumen and in Laravel. |
| `src/Http/Middleware/VerifyJwt.php` | Middleware `jwt`: no valid token → `401 {"message": "Unauthenticated."}`; otherwise `$request->user()` is the `JwtUser`. |
| `src/Http/Middleware/EnsureAdmin.php` | Middleware `admin`, always after `jwt` (`['middleware' => ['jwt', 'admin']]`): role not `admin` → `403 {"message": "This action is unauthorized."}`. |
| `src/Http/Middleware/VerifyServiceToken.php` | Middleware `service`, for `/api/internal/*` routes only: `X-Service-Token` header must equal the `service_token` secret, otherwise `401 {"message": "Unauthenticated."}`. An empty or missing secret rejects every call. |
| `src/Auth/JwtDecoder.php` | Verifies a token with Identity's public key: RS256 only, 30 s leeway, required claims `sub`, `role` (`user` or `admin`), `name`, `exp`. |
| `src/Auth/JwtUser.php` | The authenticated user from the token: `id`, `role`, `name`, `isAdmin()`. |
| `config/jwt.php` | `public_key_path` (`JWT_PUBLIC_KEY_PATH`) and `leeway` defaults, merged with the project's own `config/jwt.php`. |
| `config/internal.php` | `service_token`, read with `secret('service_token')` (file `secrets/service_token`, the same value in every service). |
| `src/Integration/EventPublisher.php` | `publish($routingKey, $data)`: wraps `$data` in the envelope of `docs/events.md` (`event` built from the routing key, new `id`, `occurred_at` in UTC) and sends it, persistent, to the `movieplatform.events` exchange. Returns the envelope. |
| `src/Integration/RabbitMqConnection.php` | One connection per process, opened on first use; declares the exchange (topic, durable). Shared by the publisher and the consumer. |
| `config/rabbitmq.php` | `host` and `port` (`RABBITMQ_HOST`, `RABBITMQ_PORT`), `user` and `password` from `secret('rabbitmq_user')` and `secret('rabbitmq_password')`, `exchange`. |

## Publishing an event

Publish **after** the transaction commits, so an event never describes data that was rolled back. The payload is
exactly the one in `docs/events.md`.

```php
$user = DB::transaction(fn () => User::create($validated));

$this->events->publish('user.registered', [
    'user_id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
]);
```

`$this->events` is an `EventPublisher` injected in the constructor. PHP's `sockets` extension must be enabled
(`README.md`, Requirements).

## Rules

- **Works in both frameworks.** Only use features that exist in Illuminate 10 **and** 12. Something new from
  Laravel 11/12 breaks every Lumen service.
- **Test both.** The package's own tests run on Laravel 12 (Orchestra Testbench). Lumen is covered by the services'
  tests, for example Identity's `JwtMiddlewareTest`. Run both before opening a PR.

```powershell
cd packages/common
composer install
composer test
```
