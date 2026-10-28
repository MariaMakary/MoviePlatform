# Gateway

Laravel 12 web app on port 8000: the whole UI (Blade + Livewire + Tailwind) and the admin dashboard. Owner: Joy, but
everyone builds the pages of their own features. It owns **no business data**: every page gets its data from the
services. Sessions and cache are in Redis (`predis`).

## Structure

```
app/Livewire/<Module>/         Auth, Catalog, Engagement, Social, Intelligence, Notifications, Admin
app/Services/Clients/          BaseClient + one client per service (IdentityClient, CatalogClient, …)
app/Services/Aggregators/      combine several services for one page (e.g. MoviePageAggregator)
resources/views/components/    shared: x-movie-card, x-rating-stars, x-avatar, x-button, x-modal, x-empty-state, x-service-unavailable
resources/views/livewire/<module>/
resources/js/                  Echo (Reverb), Leaflet
```

## Rules

- A Livewire component **never calls `Http::` directly**: it uses a Client or an Aggregator.
- Clients send the session's JWT (`Authorization: Bearer`), with a 3-second timeout and 2 retries for `GET` only, and throw `ServiceUnavailable` when a service is down (`docs/api/conventions.md`, section 10).
- **Graceful fallback:** a section whose service is down shows `<x-service-unavailable/>`; the page never crashes.
- Always reuse the shared layout and Blade components. Escape output with `{{ }}`, never `{!! !!}` with user input.

## Real time

- The **Reverb** server runs from this project: `php artisan reverb:start` (port 8080).
- Laravel Echo in `resources/js` listens to `room.{id}` (MessageSent), `movie-night.{id}` (VotesUpdated) and
  `user.{id}` (NotificationCreated). Private channels are authorized by this app's `/broadcasting/auth`.

## Running

`composer run dev` starts `php artisan serve --port=8000`, `php artisan reverb:start` and `npm run dev`. Remove
`php artisan pail` from Laravel's default `dev` script: it needs `pcntl`, which does not exist on Windows.
Tests: `php artisan test`.
