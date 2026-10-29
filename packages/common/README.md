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
| `src/Exceptions/JsonExceptionRenderer.php` | Turns any exception into the error JSON of `docs/api/conventions.md` §6: 401, 403, 404 (`"<Model> not found."` for `findOrFail`), 405, 422 with `errors`, any `HttpException` with its status and message, everything else `500 {"message": "Server error."}`. Every service's `app/Exceptions/Handler.php` calls it. In Lumen, the provider also hooks `Controller::buildResponseUsing()` so `$this->validate()` returns `message` + `errors`. |
| `src/Http/Resources/PaginatedCollection.php` | List responses: `PaginatedCollection::fromQuery($query, XyzResource::class, $request)` gives `{"data": […], "meta": {current_page, last_page, total}}`, reading `?page` and `?per_page` (default 20, max 100). |

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
