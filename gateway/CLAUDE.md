# Gateway

Laravel 12 web app on port 8000: the whole UI (Blade + Livewire + Tailwind) and the admin dashboard. Owner: Joy, but
everyone builds the pages of their own features. It owns **no business data**: every page gets its data from the
services. Sessions and cache are in Redis (`predis`).

## Structure

```
app/Livewire/<Module>/         Auth, Catalog, Engagement, Social, Intelligence, Notifications, Admin
app/Services/Clients/          BaseClient + one client per service (IdentityClient, CatalogClient, …)
app/Services/Aggregators/      combine several services for one page (e.g. MoviePageAggregator)
resources/views/components/    shared: x-logo, x-nav-link, x-date, x-flash, x-movie-card, x-movie-row (+ .item), x-rating-stars, x-avatar, x-button, x-input, x-modal, x-empty-state, x-service-unavailable, x-error-page, x-search-spinner, x-icons.star / bookmark / plus / weather
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
  | 429 | `ValidationException` on `form` with the service's message (`@error('form')`) |
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
  guards the page: the service still checks the token on every admin endpoint. The side menu finds the current page
  with `Livewire::originalPath()`, so the link stays active after a Livewire update (`routeIs()` is false there).
  Pages so far: Dashboard, **Movies** (`/admin/movies`, `App\Livewire\Admin\Movies`: Catalog's admin endpoints; edit
  and delete in `x-modal`s; every change flashes and redirects to the same search and page), **Reviews**
  (`/admin/reviews`, `App\Livewire\Admin\Reviews`: Engagement's admin endpoints; hide, unhide and delete go through
  one `x-modal` confirmation, `confirm($action, $id)` then `apply`) and **Users** (`/admin/users`,
  `App\Livewire\Admin\Users`: blocks, unblocks and deletes through Identity only, whose events do the rest in the other
  services; one `x-modal` confirmation, `confirm($action, $id, $name)` then `apply`; the admin's own row has no
  buttons). Reviews and Users go back to the last page that still has rows when an action empties the current one.
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
- **Responsive** (375, 768 and 1280 px, no horizontal page scroll): the navbar shows its links from `xl` (1280 px),
  the search box from `2xl` (a search icon in between) and below `xl` the menu button. Wrap a table in
  `overflow-x-auto` and give it a `min-w-[…]`, so it scrolls instead of squeezing. Text a user typed (titles, bios,
  descriptions, reviews, messages) gets `wrap-anywhere`, so a long word or link breaks instead of widening the page.
  A dropdown wider than about 250 px is `fixed inset-x-4` on phones and `sm:absolute` from 640 px. A row with a title
  and a button gets `flex-wrap`.
- **Errors and loading:** `resources/views/errors/` has the custom 403, 404, 419, 429, 500 and 503 pages, built with
  `<x-error-page>`. A 403/404 from a service (`ServiceRequestException`) shows the service's message. 500 uses no
  navbar or Livewire, so it renders even when the layout fails. You only see them with `APP_DEBUG=false`; with `true`
  Laravel shows its debug page. `<x-button>` with `wire:click` (or `wire:target`) shows a spinner and is disabled while
  that action runs, so give a submit button `wire:target="<form method>"`; a plain `<button wire:click>` needs
  `wire:loading.attr="disabled" wire:target="…"`. A live search input gets `<x-search-spinner target="q" />` in a
  `relative` wrapper. Every list shows its data, `<x-empty-state>` (a dropdown or a small block: one short line) or
  `<x-service-unavailable>`, never nothing.
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
- **Add to calendar:** `<livewire:intelligence.add-to-calendar>` takes the event's fields: `title`, `start` (a
  service's UTC date-time, or a `Y-m-d` date with `:all-day="true"`), and optional `description`, `location` and `url`
  (`docs/api/intelligence.md`, "Calendar"). It offers an `.ics` download and a Google Calendar link through
  Intelligence; Google opens with a redirect, not `window.open`, because a popup opened after the request is blocked.
  Used for a movie's release (movie page) and a finalized movie night.
- **Changing a movie night** (finalize, cancel): the page flashes the result and redirects to itself, also on an
  error, so the next request tries Social again even when it just answered `503` (Catalog down during finalize).
- **TMDB images:** build poster, backdrop and profile URLs with `App\Services\TmdbImage::url($path, 'w342')` (size
  from TMDB: `w185`, `w342`, `w780`, `original`…). Never build `image.tmdb.org` URLs by hand.

## Rate limits

- Every web request (pages and Livewire updates) goes through `throttle:web`: `config('throttle.web')` per logged-in
  user, else per IP (`AppServiceProvider`). Over it: Laravel's 429 page.
- Public forms that call Identity (login, register, forgot and reset password) also use the
  `App\Livewire\Concerns\ThrottlesByIp` trait: `if ($this->tooManyAttempts('login')) return;` first thing in the
  action, with the limits in `config/throttle.php`. Over it, the form shows "Too many attempts. Try again in N
  seconds." and Identity is not called. A successful login calls `clearAttempts('login')`, so only failed logins
  count (a class shares one Wi-Fi IP). Identity limits the same forms per email (`docs/api/identity.md`), and every
  service limits each user to 60 requests per minute; all limits are listed in `docs/security.md`.

## Real time

- The **Reverb** server runs from this project: `php artisan reverb:start` (port 8080), started by `composer run dev`.
  Gateway, Social and Notification share `REVERB_APP_ID` / `REVERB_APP_KEY` (`.env`) and the secret in
  `secrets/reverb_app_secret`, read with `secret('reverb_app_secret')`. Social and Notification (Lumen 10) broadcast
  with the `pusher` driver pointed at Reverb (their `config/broadcasting.php`).
- Laravel Echo (`resources/js/echo.js`, `window.Echo`) listens to `room.{id}` (MessageSent), `movie-night.{id}`
  (VotesUpdated) and `user.{id}` (NotificationCreated). Private channels are authorized by this app's
  `/broadcasting/auth` in `routes/channels.php`, with the `broadcast` guard: the session user from `AuthSession` as a
  `GenericUser` (`$user->id`, `name`, `role`). Add `['guards' => ['broadcast']]` to every channel.
- Who may listen: guests never (no session user → `403` on every channel). `room.{id}`: any logged-in user (rooms
  are public). `movie-night.{id}`: only participants, checked with Social's `GET /api/movie-nights/{id}` and the
  user's token (`SocialClient::movieNight`); Social's `404` (not a participant) or Social down refuses. `user.{id}`:
  only that user.

- A Livewire component listens with Livewire's Echo listeners in `getListeners()`, e.g.
  `"echo-private:movie-night.{$this->nightId},.VotesUpdated" => '$refresh'` (the dot: the services send the name of
  `broadcastAs()` as it is). `$refresh` re-renders the component, which loads its data again in `render()`, so the
  page stays right even if an event is missed. `App\Livewire\Social\Shortlist` (the voting section of the movie night
  page) does this; the page itself also listens, for the host only, so the Finalize box shows the current leader.
- The notification bell (`App\Livewire\Notifications\Bell`) listens to `user.{id}` `.NotificationCreated` the same
  way. Its view polls every 30 seconds only while `window.Echo` is not connected (Reverb down), as a fallback.
- Livewire sends a component's listeners only when the page loads, so a channel whose id comes from a service must be
  known in `mount()`. **Discussion room** (`/movies/{id}/discussion`, `App\Livewire\Social\RoomPage`; list of rooms at
  `/discussions`): it loads the room and its latest messages in `mount()`; if Social was down and "Try again" works,
  it redirects to itself so the listener is registered. Messages pile up (older ones from "Load older", new ones from
  `.MessageSent` through `receive()`, which appends without duplicates), so it keeps them in properties instead of
  reloading them in `render()`; only the authors' names and avatars are loaded there (Identity bulk lookup, missing
  ones only). It dispatches `room-message-added` and `room-older-loaded` for the Alpine auto-scroll.

## Running

`composer run dev` runs `composer install` and `npm install --no-save` (exactly the versions in the lock files; nobody runs them by
hand after a pull), then starts `php artisan serve --port=8000`, `npm run dev` and `php artisan reverb:start`. Never add `php artisan pail`: it needs `pcntl`, which does not exist on Windows.

No automated tests in the Gateway: each task's "Done when" in Azure Boards is checked by hand in the browser.
