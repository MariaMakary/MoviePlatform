# Gateway

Laravel 12 web app on port 8000: the whole UI (Blade + Livewire + Tailwind) and the admin dashboard. Owner: Joy, but
everyone builds the pages of their own features. It owns **no business data**: every page gets its data from the
services. Sessions and cache are in Redis (`predis`).

## Structure

```
app/Livewire/<Module>/         Auth, Catalog, Engagement, Social, Intelligence, Notifications, Admin
app/Services/Clients/          BaseClient + one client per service (IdentityClient, CatalogClient, …)
app/Services/Aggregators/      combine several services for one page (e.g. MoviePageAggregator)
resources/views/components/    shared: x-logo, x-nav-link, x-date, x-flash, x-movie-card, x-rating-stars, x-avatar, x-button, x-modal, x-empty-state, x-service-unavailable, x-icons.star
resources/views/layouts/       app.blade.php (navbar, flash area, footer) + partials/ (navigation, search, user-menu, notification-bell, footer)
resources/views/livewire/<module>/
resources/js/                  Echo (Reverb), Leaflet
```

## Rules

- A Livewire component **never calls `Http::` directly**: it uses a Client or an Aggregator.
- Clients send the session's JWT (`Authorization: Bearer`), with a 3-second timeout and 2 retries for `GET` only, and throw `ServiceUnavailable` when a service is down (`docs/api/conventions.md`, section 10).
- **Graceful fallback:** a section whose service is down shows `<x-service-unavailable/>`; the page never crashes.
- Always reuse the shared layout and Blade components. Escape output with `{{ }}`, never `{!! !!}` with user input.
  Every component, with its props and example data, is shown at **http://localhost:8000/dev/components** (only when
  `APP_ENV=local`). Check it before building UI; add a section there when you add a shared component.
- Colors: use the theme tokens `jet`, `cerulean` and `neon-rose` (`bg-cerulean`, `text-neon-rose`…), never new hex values.
- Full-page Livewire components use `layouts/app.blade.php` automatically (`config/livewire.php`); set the tab title
  with `#[Title('…')]`. Flash messages: `session()->flash('success' | 'error' | 'info', '…')`.
- **Dates:** services send UTC. Show them with `<x-date :value="$movieNight['scheduled_at']" />` (optional `format`),
  which uses `APP_DISPLAY_TIMEZONE` (Asia/Beirut). Convert a date typed in a form back with
  `App\Services\DisplayTime::toService($input)` before sending it to a service. Never format service dates by hand.
- **TMDB images:** build poster, backdrop and profile URLs with `App\Services\TmdbImage::url($path, 'w342')` (size
  from TMDB: `w185`, `w342`, `w780`, `original`…). Never build `image.tmdb.org` URLs by hand.

## Real time

- The **Reverb** server runs from this project: `php artisan reverb:start` (port 8080).
- Laravel Echo in `resources/js` listens to `room.{id}` (MessageSent), `movie-night.{id}` (VotesUpdated) and
  `user.{id}` (NotificationCreated). Private channels are authorized by this app's `/broadcasting/auth`.

## Running

`composer run dev` starts `php artisan serve --port=8000` and `npm run dev`; `php artisan reverb:start` is added
when Reverb is installed. Never add `php artisan pail`: it needs `pcntl`, which does not exist on Windows.

No automated tests in the Gateway: each task's "Done when" in Azure Boards is checked by hand in the browser.
