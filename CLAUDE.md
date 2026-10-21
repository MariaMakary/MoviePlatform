# MoviePlatform – Project Guide for Claude Code

Web2 course team project (Antonine University): a movie discovery and social platform.
Team: **Maria** (lead), **Joy**, **Joe**, **Tonio**.
Full reference: `docs/MoviePlatform-Service-Reference.docx` · Git workflow: `docs/MoviePlatform-Git-Workflow-Guide.docx`.

---

## 1. Architecture

**Gateway (Laravel MVC web app) + 6 Laravel microservices.** Each service is a separate Laravel project with **its own PostgreSQL database**. The Gateway owns no business data. It only calls service APIs.

| Component | Port | Owns | Owner |
|---|---|---|---|
| `gateway/` | 8000 | All UI (Blade + Livewire), session, admin dashboard UI | Joy (everyone builds their own pages) |
| `services/identity` | 8001 | Users, auth, social login, roles, JWT issuing | Maria (social login: Joy) |
| `services/catalog` | 8002 | TMDB integration, local movie DB, search, genres, people | Joy (search: Tonio · genres/people/caching: Maria · internal endpoints: Joe) |
| `services/engagement` | 8003 | Ratings, reviews, watchlist, history, custom lists, moderation | Tonio |
| `services/social` | 8004 | Follows, profiles, privacy, activity feed, movie nights, chat rooms | Maria (feed, movie nights) · Joe (follows, profiles, privacy, chat) |
| `services/intelligence` | 8005 | Recommendations, sentiment, weather, calendar | Joe (recommendations: Maria) |
| `services/notification` | 8006 | User notifications | Joe |

Infrastructure: **PostgreSQL 16** (5432), **RabbitMQ** (5672, UI 15672), **Redis** (6379, cache/sessions only), **Reverb** (8080, WebSockets).

```
MoviePlatform/
├── gateway/
├── services/{identity,catalog,engagement,social,intelligence,notification}/
├── docs/            ERDs, architecture, api/<service>.md, events.md, sprints/
├── docker/          postgres init script, rabbitmq definitions
├── secrets/         git-ignored – real secret values
├── secrets.example/ committed – empty files with the expected names
├── docker-compose.yml
└── CLAUDE.md
```

---

## 2. Tech stack

- **Laravel 12**, PHP 8.3, **Blade + Livewire + Tailwind** (Gateway only)
- **PostgreSQL 16**: use `jsonb`, `ILIKE` for case-insensitive search
- **RabbitMQ** via `php-amqplib` for integration events (NOT Laravel queue jobs between services)
- **Redis**: cache (TMDB, weather), Gateway sessions, one-time login codes. **Not for events.**
- **Laravel Reverb** + Echo for real time (chat, live votes, live notification bell)
- **JWT RS256**: Identity signs with the private key, every service verifies with the public key
- External APIs: TMDB, OpenWeatherMap, Hugging Face Inference API (sentiment), Leaflet + OpenStreetMap/Overpass (maps, browser-side), Laravel Socialite (Google/GitHub)

---

## 3. Golden rules (always follow)

1. **A service never reads or writes another service's database.** Get other data through that service's API or through events.
2. **No foreign keys across services.** `user_id` = Identity `users.id`, `movie_id` = Catalog `movies.id` (never `tmdb_id`). Store them as plain `uuid` columns.
3. **Validate inside each service** (FormRequest), even if the Gateway already validated.
4. **Never return raw models.** Always use API Resources.
5. **Never hardcode or commit secrets.** Read them with `secret('name')` in config files only.
6. **Contracts first.** If you change an endpoint or an event, update `docs/api/<service>.md` or `docs/events.md` in the same change, and tell the services that use it.
7. **Graceful fallback.** If a service is down, the Gateway shows `<x-service-unavailable/>` for that section. The page never crashes.
8. Code, comments, commits and docs are in **English**.

---

## 4. Service internal structure (identical in every service)

```
app/
├── Http/
│   ├── Controllers/Api/      thin: ~10 lines per method, no queries, no business logic
│   ├── Requests/             validation (one class per action)
│   ├── Resources/            JSON output = the "View" of an API
│   └── Middleware/           VerifyJwt, EnsureAdmin, VerifyServiceToken, CorrelationId
├── Models/                   Eloquent: relationships, casts, scopes
├── Services/                 business logic lives here
├── Policies/                 ownership / role checks
├── Integration/
│   ├── Events/               outgoing events (one class per event)
│   ├── Handlers/             incoming event handlers (must be idempotent)
│   ├── EventPublisher.php
│   └── Clients/              the ONLY place making HTTP calls to other services / external APIs
├── Console/Commands/         events:consume, events:replay-dlq, sync commands
├── Exceptions/               JSON error rendering
└── Support/secret.php        Docker secrets helper
config/events.php             routing keys published / bound + handler map
routes/api.php                grouped by middleware: jwt, admin, service
```

**Request flow:** `routes/api.php → Middleware → FormRequest → Controller → Policy → Service → Model → EventPublisher → Resource`

### Gateway structure

```
app/Livewire/<Module>/         Auth, Catalog, Engagement, Social, Intelligence, Notifications, Admin
app/Services/Clients/          BaseClient + one client per service (IdentityClient, CatalogClient, …)
app/Services/Aggregators/      combine several services for one page (e.g. MoviePageAggregator)
resources/views/components/    shared: x-movie-card, x-rating-stars, x-avatar, x-button, x-modal, x-empty-state, x-service-unavailable
resources/views/livewire/<module>/
resources/js/                  Echo (Reverb), Leaflet
```

**Gateway rule:** a Livewire component never calls `Http::` directly. Use a Client (or an Aggregator). Always reuse the shared Blade components and layout.

---

## 5. Communication

### REST (sync, request/response)
- Gateway → service: `Authorization: Bearer <JWT>` from the session.
- JWT claims: `sub` (user UUID), `role` (`user`|`admin`), `name`, `exp` (2h).
- Service → service internal calls: header `X-Service-Token`, only on `/api/internal/*` routes.
- Every call carries `X-Correlation-ID`.
- `BaseClient`: 3s timeout, 2 retries, throws `ServiceUnavailable` on connection errors.

### API conventions
- JSON only. Errors: `{"message": "...", "errors": {"field": ["..."]}}` with 401/403/404/422/503.
- Pagination: `{"data": [...], "meta": {"current_page", "last_page", "total"}}`.
- Dates: ISO-8601 (UTC).
- Every service exposes `GET /api/health`.

### Events (async) – RabbitMQ
- Topic exchange **`movieplatform.events`**. Routing key = event name in dot form (`review.created`).
- One durable queue per service: `<service>.events`, plus a dead-letter queue `<service>.events.dlq`.
- Envelope:
  ```json
  { "event": "ReviewCreated", "id": "uuid", "occurred_at": "2026-11-03T18:20:00Z",
    "correlation_id": "uuid", "data": { } }
  ```
- Publish **after** the DB transaction commits. Handlers ack on success, retry 3×, then go to the DLQ.
- Handlers are **idempotent**: skip event ids already in `processed_events`.

| Event (routing key) | Publisher | Consumers |
|---|---|---|
| `user.registered` | Identity | Notification |
| `user.genres_updated` | Identity | Intelligence |
| `user.blocked` / `user.deleted` | Identity | Engagement, Social, Notification |
| `movie.updated` | Catalog | Intelligence |
| `rating.added` | Engagement | Intelligence, Social |
| `review.created` / `review.updated` | Engagement | Intelligence, Social |
| `movie.watched` | Engagement | Intelligence, Social |
| `list.created` | Engagement | Social |
| `review.analyzed` | Intelligence | Engagement |
| `user.followed` | Social | Notification |
| `movie_night.invite_sent` / `invite_answered` / `finalized` | Social | Notification |

The exact payload fields are in `docs/events.md`. Follow them exactly.

### Real time – Reverb
- Social broadcasts `MessageSent` on `room.{id}` and `VotesUpdated` on `movie-night.{id}`.
- Notification broadcasts `NotificationCreated` on `user.{id}`.
- Private channels are authorized by the Gateway's `/broadcasting/auth`.

---

## 6. Database conventions

- PostgreSQL 16, one database + one user per service (`identity_db`, `catalog_db`, …).
- **UUID primary keys everywhere**: `$table->uuid('id')->primary()` + the `HasUuids` trait on models (time-ordered UUIDs).
- Inside a service: `$table->foreignUuid('user_id')->constrained()`. References to another service: plain `$table->uuid('movie_id')` (no FK).
- Plural snake_case tables, `created_at`/`updated_at` everywhere.
- Use `jsonb` (`$table->jsonb()`) for flexible data.
- Every schema change comes as a migration, and the ERD in `docs/erd/` is updated in the same PR.

---

## 7. Secrets and configuration

- Secrets are **Docker secrets**, mounted at `/run/secrets/<name>`. Values live in the git-ignored `secrets/` folder.
- Read them only in `config/*.php` with `secret('tmdb_api_key')`. It falls back to `.env` outside Docker.
- `.env` holds **non-secret** settings only (URLs, ports, DB names, `APP_DEBUG`).
- Never print, log or commit secret values. Never add real keys to `.env.example` or `secrets.example/`.

---

## 8. Running the project

```bash
docker compose up -d                                   # start everything
docker compose ps                                      # check health
docker compose logs -f <service>                       # logs
docker compose exec <service> php artisan migrate      # migrations for one service
docker compose exec <service> php artisan db:seed
docker compose exec <service> php artisan events:consume     # (runs in the worker container)
docker compose exec <service> php artisan events:replay-dlq
```

URLs: Gateway http://localhost:8000 · RabbitMQ UI http://localhost:15672 · services on ports 8001–8006.

---

## 9. Git workflow

- Branches are created **from the Azure DevOps task** ("Create a GitHub branch"), always **based on `develop`**.
- Branch names: `feature/<taskId>-short-name`, `fix/…`, `docs/…`, `chore/…`.
- Commit messages include `AB#<taskId>`, e.g. `Add login form validation AB#1045`.
- Pull requests target **`develop`** and their description ends with **`Fixes AB#<taskId>`**.
- 1 approval from a member who owns a different service. Use **Squash and merge**, then delete the branch.
- **Never push directly to `main` or `develop`.** `main` is updated only at the end of a sprint.

---

## 10. Definition of done (check before opening a PR)

- [ ] The task's "Done when" criteria in Azure Boards are met
- [ ] Follows the service structure (Requests, Services, Resources, Policies, Integration)
- [ ] Inputs validated, ownership checked, no raw models returned
- [ ] Events match `docs/events.md`; handlers are idempotent
- [ ] Gateway pages use the shared layout and components and handle a service being down
- [ ] `docs/api/<service>.md`, `docs/events.md` or the ERD updated if something changed
- [ ] No secrets, `.env`, `vendor/` or `node_modules/` committed
- [ ] Runs with `docker compose up`

---

## 11. Instructions for Claude

- Before coding, **read the task description** (the user will paste it or give the Azure task id) and the relevant `docs/api/<service>.md` / `docs/events.md`.
- Work **only in the service the task belongs to**, plus its Gateway pages. If another service needs a change, say so instead of editing it silently.
- **Never** add cross-service database queries or foreign keys, even as a "quick fix".
- Follow the folder structure and naming above. Match the existing code style of the service.
- Ask before changing an API contract, an event payload, `docker-compose.yml` or anything in the shared service template (middleware, EventPublisher, consumer, `secret()`).
- Prefer small, focused changes that fit one task and one pull request.
- When you add or change an endpoint or event, update the docs in the same change.
- Keep explanations short and suggest the commit message with `AB#<id>`.
