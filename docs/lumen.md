# Working with Lumen

The 6 services are **Lumen 10** projects (required by the course). The Gateway is **Laravel 12**. Lumen is Laravel with
parts removed: Eloquent, routing, validation and the container are the same code, but setup is manual and some tools
are missing. Follow these rules so every service is built the same way.

## 0. Folder structure (identical in every service)

```
app/
├── Http/
│   ├── Controllers/Api/      thin: ~10 lines per method, no queries, no business logic
│   ├── Requests/             validation rules (one class per action, static rules())
│   ├── Resources/            JSON output = the "View" of an API
│   └── Middleware/           service-specific only (jwt, admin, service come from packages/common)
├── Models/                   Eloquent: relationships, casts, scopes
├── Services/                 business logic lives here
├── Policies/                 ownership / role checks
├── Integration/
│   ├── Events/               outgoing events (one class per event)
│   ├── Handlers/             incoming event handlers (must be idempotent)
│   └── Clients/              the ONLY place making HTTP calls to other services / external APIs
├── Console/Commands/         service-specific commands (sync jobs, …)
└── Exceptions/               JSON error rendering (uses packages/common)
config/events.php             routing keys published / bound + handler map
routes/api.php                grouped by middleware: jwt, admin, service
```

**Request flow:** `routes/api.php → Middleware → Request rules → Controller → Policy → Service → Model →
EventPublisher (after commit) → Resource`

## 1. What Lumen does not have

| Missing | Use instead |
|---|---|
| `php artisan serve` | `php -S 127.0.0.1:800X -t public` (from the service folder) |
| `php artisan key:generate` | A random 32-character `APP_KEY` in `.env` (see the root README) |
| `make:controller`, `make:model`, `make:request`, `make:resource`, `make:factory`, `make:test` | Create the file by hand (templates below). Only `make:migration` and `make:seeder` exist. |
| FormRequest classes | A request class with static `rules()` + `$this->validate()` (section 4) |
| `Route::` facade, `routes/web.php` | `$router` in `routes/api.php` (section 3) |
| Package auto-discovery | Register providers in `bootstrap/app.php` |
| `php artisan test`, `route:list`, `config:cache` | `vendor/bin/phpunit`; no equivalent for the others |
| Views, sessions, cookies | Nothing: services return JSON only. Pages live in the Gateway. |

**Before installing a package**, check that it supports **Lumen 10 / Illuminate 10**. Laravel-only packages (Sanctum,
Breeze, Livewire, …) do not work in a service.

## 2. `bootstrap/app.php`

This file replaces Laravel's automatic setup. Every service has the same one; don't remove its lines.

- `$app->withFacades()` and `$app->withEloquent()` are on.
- `$app->register(MoviePlatform\Common\CommonServiceProvider::class)` loads `packages/common` (the `jwt` guard and
  middleware).
- `routes/api.php` is loaded under the **`/api` prefix**, with controllers in `App\Http\Controllers`.

When you add:

| You add | Also add to `bootstrap/app.php` |
|---|---|
| A config file `config/reviews.php` | `$app->configure('reviews');` |
| A service provider | `$app->register(App\Providers\XyzServiceProvider::class);` |
| Policies | `$app->register(App\Providers\AuthServiceProvider::class);` (section 6) |
| Service-specific middleware | `$app->routeMiddleware(['name' => App\Http\Middleware\Xyz::class]);` |

`env()` is only read in `config/*.php` (secrets with `secret()`), never in app code.

## 3. Routes

`routes/api.php`. The `/api` prefix is added automatically: `/reviews` here is `/api/reviews`.

```php
<?php

$router->get('/health', '\MoviePlatform\Common\Http\Controllers\HealthController');

$router->get('/movies/{movieId}/reviews', 'Api\ReviewController@index');

$router->group(['middleware' => 'jwt'], function ($router) {
    $router->post('/reviews', 'Api\ReviewController@store');
    $router->put('/reviews/{id}', 'Api\ReviewController@update');
    $router->delete('/reviews/{id}', 'Api\ReviewController@destroy');
});
```

Controllers are referenced as `'Api\Controller@method'` strings. Route parameters arrive as method arguments
(`update(Request $request, string $id)`).

## 4. Controllers and validation

Controllers go in `app/Http/Controllers/Api`, extend `App\Http\Controllers\Controller` and stay thin: validate, call a
Service, return a Resource. The constructor gets its dependencies injected.

One request class per action in `app/Http/Requests`, with only the rules:

```php
<?php

namespace App\Http\Requests;

class StoreReviewRequest
{
    public static function rules(): array
    {
        return [
            'movie_id' => ['required', 'uuid'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, StoreReviewRequest::rules());

        $review = $this->reviews->create($request->user()->id, $data);

        return (new ReviewResource($review))->response()->setStatusCode(201);
    }
}
```

`$this->validate()` returns only the validated fields. On failure it stops with **422** and
`{"message": "The given data was invalid.", "errors": {…}}`: `packages/common` registers Lumen's
`Controller::buildResponseUsing()` hook. Don't format validation errors by hand in controllers.

All other errors are rendered by `MoviePlatform\Common\Exceptions\JsonExceptionRenderer`, which every service's
`app/Exceptions/Handler.php` calls. Just throw: `abort(404, 'Movie not found.')`, `findOrFail()` (gives
`"Movie not found."`), `$this->authorize()` (403). Anything unexpected becomes `500 {"message": "Server error."}` and
the real error is logged. Policies need the logged-in user, so call `$this->authorize()` only on `jwt` routes.

## 5. Models, migrations and Resources

- Migrations: `php artisan make:migration create_reviews_table`. UUID primary keys: `$table->uuid('id')->primary()`.
  A reference to another service's data is a plain `$table->uuid('movie_id')`, never a foreign key.
- Models in `app/Models`, created by hand, with the `HasUuids` trait:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'movie_id', 'body'];
}
```

- Never return a model: always an API Resource from `app/Http/Resources` (`extends JsonResource`). Dates with
  `->toIso8601ZuluString()`.
- Every list endpoint returns a `MoviePlatform\Common\Http\Resources\PaginatedCollection`. It reads `?page` and
  `?per_page` (default 20, capped at 100) and outputs exactly the pagination format of `docs/api/conventions.md`:

```php
return PaginatedCollection::fromQuery(Review::where('movie_id', $movieId), ReviewResource::class, $request);
```

  When a Service already built the paginator, use `new PaginatedCollection($paginator, ReviewResource::class)` and get
  the sizes with `PaginatedCollection::perPage($request)` and `PaginatedCollection::page($request)`.

## 6. Authentication and policies

- Protected routes use the `jwt` middleware (from `packages/common`). Then `$request->user()` is the logged-in user
  from the token: `->id`, `->role`, `->name`, `->isAdmin()`. There is no `User` lookup in other services.
- Policies: register `App\Providers\AuthServiceProvider` in `bootstrap/app.php`. **First delete the skeleton's
  `viaRequest('api', …)` example** in its `boot()` and register the policies instead:

```php
public function boot(): void
{
    Gate::policy(Review::class, ReviewPolicy::class);
}
```

- In a controller: `$this->authorize('update', $review);` returns 403 when the policy refuses.

## 7. Tests

Tests live in `tests/`, use the `Tests` namespace, extend `Tests\TestCase`, and run with `vendor/bin/phpunit`.
Lumen's test helpers differ from Laravel's:

| Laravel | Lumen |
|---|---|
| `$this->getJson('/api/x')` | `$this->get('/api/x')` or `$this->json('GET', '/api/x')` |
| `->assertOk()` / `->assertStatus(201)` | `->seeStatusCode(200)` / `->seeStatusCode(201)` |
| `->assertExactJson([...])` | `->seeJsonEquals([...])` |
| `->assertJsonFragment([...])` | `->seeJson([...])` |

```php
$this->json('POST', '/api/reviews', ['movie_id' => $movieId, 'body' => 'Great'], ['Authorization' => "Bearer $token"])
    ->seeStatusCode(201)
    ->seeJson(['body' => 'Great']);
```

Use the `DatabaseTransactions` trait in tests that write to the database, so nothing stays behind. For tokens, see how
`services/identity/tests/JwtMiddlewareTest.php` signs one with the test keys in `tests/Fixtures/jwt`.

## 8. Shared code

Code in `packages/common` runs in Lumen 10 **and** Laravel 12: only use features that exist in both Illuminate 10 and
12, and run the tests of a Lumen service (Identity) as well as the package's own. See `packages/common/README.md`.
