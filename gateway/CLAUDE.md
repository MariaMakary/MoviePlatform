# Gateway

Laravel 12 web app on port 8000: the whole UI (Blade + Livewire + Tailwind) and the admin dashboard. Owner: Joy, but
everyone builds the pages of their own features. It owns **no business data**: every page gets its data from the
services. Sessions and cache are in Redis (`predis`).

## Structure

```
app/Livewire/<Module>/         Auth, Catalog, Engagement, Social, Intelligence, Notifications, Admin
app/Services/Clients/          BaseClient + one client per service (IdentityClient, CatalogClient, …)
app/Services/Aggregators/      combine several services for one page (e.g. MoviePageAggregator)
resources/views/components/    shared: x-logo, x-nav-link, x-date, x-flash, x-movie-card, x-movie-row (+ .item), x-rating-stars, x-avatar, x-button, x-input, x-modal, x-empty-state, x-service-unavailable, x-icons.star / bookmark / plus / weather
resources/views/layouts/       app.blade.php (navbar, flash area, footer) + partials/ (navigation, search, user-menu, notification-bell, footer)
resources/views/livewire/<module>/
resources/js/                  Echo (Reverb), Leaflet
```

## Rules

- A Livewire component **never calls `Http::` directly**: it uses a Client or an Aggregator.
- Clients send the session's JWT (`Authorization: Bearer`), with a 3-second timeout and 2 retries for `GET` only, and throw `ServiceUnavailable` when a service is down (`docs/api/conventions.md`, section 10).
- **Clients** (`app/Services/Clients`): add one public method per endpoint to that service's client, built on the
  protected `get` / `post` / `put` / `patch` / `delete` of `BaseClient`, or `upload($path, $field, $contents, $fileName)`
  for a multipart file (e.g. `IdentityClient::uploadAvatar`). They return the decoded JSON (`null` for 204);
  `postForFile($path, $data)` returns the raw body of a non-JSON answer (e.g. `IntelligenceClient::calendarIcs`).
  Service URLs come from `IDENTITY_URL` … `NOTIFICATION_URL` in `.env`. The JWT is read from `session('jwt')`
  (`BaseClient::SESSION_TOKEN`): login stores it there.

  | Service answers | You get |
  |---|---|
  | 2xx | the JSON as an array |
  | 422 | `ValidationException`: errors show under the form fields (`@error('score')`) |
  | other 4xx | `ServiceRequestException` (status + the service's message; renders the 403/404 page) |
  | no connection or 5xx | `ServiceUnavailable` (logged as a warning) |

  Exception: `health()` returns the body of a `503` too (`status: degraded` and the part that failed), so the admin
  dashboard can show what is wrong; only a connection error throws.

  **A service that is down is skipped for the rest of the request.** Once a call ends in `ServiceUnavailable`
  (no connection, timeout or 5xx), `ServiceOutages` (bound with `app()->scoped()`) remembers that service, and every
  later call to it in the same request throws `ServiceUnavailable` at once without sending anything. So a page with
  20 components that all call a hanging service waits for one timeout, not 20. A 4xx or 422 means the service is up
  and is not remembered. The warning is logged once per service per request. The next request (every Livewire update
  too) tries the service again. `health()` always calls the service, so the admin status page is never skipped.
- **Logged-in user:** `App\Services\AuthSession` owns the session keys. Login and register (`App\Livewire\Auth`, through
  the `SignsIn` trait) call `start()`: a new session id, then the token (`jwt`), its `jwt_expires_at` and the user
  (`id`, `name`, `role`, `avatar`). Read the user with `app(AuthSession::class)->user()` (null for guests and once the
  token has expired), never with `session('user')` directly. Form errors from a service's 422 appear under `<x-input>`
  fields automatically; other form-level messages use the `form` error key (`@error('form')`).
- **Protected pages:** add `->middleware('auth.jwt')` to the route. A guest is sent to `/login` and comes back to that
  page after logging in. It also protects the page's Livewire actions (persistent middleware).
- **Admin pages:** add the route to the `/admin` group in `routes/web.php` (`auth.jwt` sends guests to `/login`,
  `admin.page` gives others a 403), wrap the view in `<x-admin.layout title="…">` and add the link to that component's
  side menu. The alias is `admin.page` because `admin` is taken by `packages/common` (JWT bearer token). This only
  guards the page: the service still checks the token on every admin endpoint.
- **Token renewal:** `RefreshJwt` runs on every web request. When a logged-in user's token expires in less than 10
  minutes, it calls Identity's `POST /api/token/refresh` and stores the new token, so an active user stays logged in.
  Identity refusing (401/403: expired, deleted or blocked) logs the user out to `/login` with a message; Identity down
  keeps the current token until it expires. After changing the user's name, call `app(AuthSession::class)->refresh()`;
  after changing only the avatar, `updateUser($user)` with Identity's answer (the token has no avatar). The profile page
  (`/profile`, `App\Livewire\Auth\Profile`) redirects to itself after each save, so the navbar shows the change at once.
- **Logout:** `POST /logout` (route `logout`) clears the session. The token is not revoked: it expires on its own.
- **Graceful fallback:** a section whose service is down shows `<x-service-unavailable/>`; the page never crashes.
  In a Livewire component, `use WithServiceFallback` and load data **in `render()`** (so "Try again" reloads it):

  ```php
  $movies = $this->fromService('trending', fn () => $catalog->trending(), []);
  ```
  ```blade
  @if ($this->isUnavailable('trending'))
      <x-service-unavailable :service="$unavailable['trending']" :retry="true" />
  @else …
  ```
  An uncaught `ServiceUnavailable` shows the 503 page in the layout. `/dev/services` (local only) shows every
  service's health this way.
- Always reuse the shared layout and Blade components. Escape output with `{{ }}`, never `{!! !!}` with user input.
  Every component, with its props and example data, is shown at **http://localhost:8000/dev/components** (only when
  `APP_ENV=local`). Check it before building UI; add a section there when you add a shared component.
- Colors: use the theme tokens `jet`, `cerulean` and `neon-rose` (`bg-cerulean`, `text-neon-rose`…), never new hex values.
- Full-page Livewire components use `layouts/app.blade.php` automatically (`config/livewire.php`); set the tab title
  with `#[Title('…')]`. Flash messages: `session()->flash('success' | 'error' | 'info', '…')`, shown by `x-flash` in the
  layout as a toast at the top right that disappears by itself (3 seconds, errors 5; hovering keeps it open). They show
  on the next page load, so flash before a redirect.
- **Dates:** services send UTC. Show them with `<x-date :value="$movieNight['scheduled_at']" />` (optional `format`),
  which uses `APP_DISPLAY_TIMEZONE` (Asia/Beirut). Convert a date typed in a form back with
  `App\Services\DisplayTime::toService($input)` before sending it to a service. Never format service dates by hand.
- **Picking people** (invites): `use PicksUsers` (`App\Livewire\Concerns`, with `WithServiceFallback`) and
  `@include('livewire.social.partials.user-picker')` with `$maxInvitees`. It searches Identity (`GET /api/users/search`,
  from 2 letters, debounced) and keeps the chosen users in `$invitees`; read their ids with `inviteeIds()`. Override
  `excludedUserIds()` to hide people already in (default: the logged-in user). Identity down shows a one-line notice in
  the picker and the rest of the form keeps working.
- **Movie night statuses** are the shared enums `MoviePlatform\Common\MovieNights\MovieNightStatus` and
  `ParticipantStatus` (`tryFrom()` on Social's strings); `livewire.social.partials.status-badges` shows both.
- **Answering an invitation:** `use AnswersInvitations` and call `answer(nightId, 'accepted' | 'declined')` from the
  view. It calls Social's RSVP, puts Social's message in `$answerError` when it refuses (night no longer open…) or is
  down, and on success flashes a toast and redirects to `afterAnswerUrl()`, which the component defines. Used by "My
  movie nights" (pending invitations) and the night page (change of mind while the night is open).
- **TMDB images:** build poster, backdrop and profile URLs with `App\Services\TmdbImage::url($path, 'w342')` (size
  from TMDB: `w185`, `w342`, `w780`, `original`…). Never build `image.tmdb.org` URLs by hand.

## Real time

- The **Reverb** server runs from this project: `php artisan reverb:start` (port 8080).
- Laravel Echo in `resources/js` listens to `room.{id}` (MessageSent), `movie-night.{id}` (VotesUpdated) and
  `user.{id}` (NotificationCreated). Private channels are authorized by this app's `/broadcasting/auth`.

## Running

`composer run dev` runs `composer install` and `npm install --no-save` (exactly the versions in the lock files; nobody runs them by
hand after a pull), then starts `php artisan serve --port=8000` and `npm run dev`; `php artisan reverb:start` is added
when Reverb is installed. Never add `php artisan pail`: it needs `pcntl`, which does not exist on Windows.

No automated tests in the Gateway: each task's "Done when" in Azure Boards is checked by hand in the browser.
