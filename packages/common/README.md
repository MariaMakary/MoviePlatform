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
| `src/CommonServiceProvider.php` | Registers the `jwt` guard and the `jwt`, `admin` and `service` route middleware, in Lumen and in Laravel, and the event consumer's commands and migration in consuming services. |
| `src/Http/Middleware/VerifyJwt.php` | Middleware `jwt`: no valid token → `401 {"message": "Unauthenticated."}`; otherwise `$request->user()` is the `JwtUser`. |
| `src/Http/Middleware/EnsureAdmin.php` | Middleware `admin`, always after `jwt` (`['middleware' => ['jwt', 'admin']]`): role not `admin` → `403 {"message": "This action is unauthorized."}`. |
| `src/Http/Middleware/VerifyServiceToken.php` | Middleware `service`, for `/api/internal/*` routes only: `X-Service-Token` header must equal the `service_token` secret, otherwise `401 {"message": "Unauthenticated."}`. An empty or missing secret rejects every call. |
| `src/Auth/JwtDecoder.php` | Verifies a token with Identity's public key: RS256 only, 30 s leeway, required claims `sub`, `role` (`user` or `admin`), `name`, `exp`. |
| `src/Auth/JwtUser.php` | The authenticated user from the token: `id`, `role`, `name`, `isAdmin()`. |
| `config/jwt.php` | `public_key_path` (`JWT_PUBLIC_KEY_PATH`) and `leeway` defaults, merged with the project's own `config/jwt.php`. |
| `config/internal.php` | `service_token`, read with `secret('service_token')` (file `secrets/service_token`, the same value in every service). |
| `src/Integration/EventPublisher.php` | `publish($routingKey, $data)`: wraps `$data` in the envelope of `docs/events.md` (`event` built from the routing key, new `id`, `occurred_at` in UTC) and sends it, persistent, to the `movieplatform.events` exchange. Returns the envelope. |
| `src/Integration/RabbitMqConnection.php` | One connection per process, opened on first use; declares the exchange (topic, durable). Shared by the publisher and the consumer. |
| `src/Integration/EventHandler.php` | Interface every event handler implements: `handle(array $data, array $envelope): void`. |
| `src/Integration/EventConsumer.php` | Declares the service's queue `<service>.events` (dead-lettering to `<service>.events.dlq`) and its bindings from `config/events.php`, consumes with prefetch 10 and manual acks. Per message: not JSON or no uuid `id` → DLQ; no handler → ack; `id` already in `processed_events` → ack; otherwise runs the handler in a transaction that also inserts into `processed_events`, retrying 3 times before rejecting to the DLQ. |
| `src/Console/ConsumeEvents.php`, `src/Console/ReplayDeadLetters.php` | `php artisan events:consume` and `php artisan events:replay-dlq`. Registered, together with the `processed_events` migration, only in a service whose `config/events.php` has a `queue`. |
| `database/migrations/…_create_processed_events_table.php` | `processed_events`: `event_id` (uuid, primary key), `event` (varchar 100), `processed_at` (timestamp), as in the ERDs. |
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

## Consuming an event

Only engagement, social, intelligence and notification consume. Each has a `config/events.php` (loaded with
`$app->configure('events')`) with its queue, the routing keys bound to it (`docs/events.md` §1) and the handler of
each event, keyed by event name:

```php
'handlers' => [
    'UserDeleted' => App\Integration\Handlers\UserDeletedHandler::class,
],
```

A handler implements `EventHandler` and reads only the fields it needs from `$data`:

```php
<?php

namespace App\Integration\Handlers;

use App\Models\Notification;
use MoviePlatform\Common\Integration\EventHandler;

class UserDeletedHandler implements EventHandler
{
    public function handle(array $data, array $envelope): void
    {
        Notification::where('user_id', $data['user_id'])->delete();
    }
}
```

`php artisan events:consume` (started by `composer run dev`) runs each handler inside a database transaction and
records the envelope `id` in `processed_events` in the same transaction, so an event that arrives twice runs once.
A handler that throws is tried again up to 3 times (4 runs in all), each time on a rolled-back transaction, then the
message goes to `<service>.events.dlq`. After fixing the bug, `php artisan events:replay-dlq` moves the dead letters
back to the main queue. Run `php artisan migrate` once to create `processed_events`.

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
