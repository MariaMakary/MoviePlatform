# MoviePlatform – Project Guide for Claude Code

Web2 course team project (Antonine University): a movie discovery and social platform. Team: **Maria**, **Joy**,
**Joe**, **Tonio**. A **Laravel 12 Gateway** (web UI, owns no business data) calls **6 Lumen 10 microservices**
(required by the course), each with **its own PostgreSQL database**. Every folder below has its own `CLAUDE.md` with
the details for that part.

| Component | Port | Owns | Owner |
|---|---|---|---|
| `gateway/` | 8000 | All UI (Blade + Livewire + Tailwind), session, admin dashboard UI | Joy |
| `services/identity` | 8001 | Users, auth, social login, roles, JWT issuing | Maria |
| `services/catalog` | 8002 | TMDB integration, local movie DB, search, genres, people | Joy |
| `services/engagement` | 8003 | Ratings, reviews, watchlist, history, custom lists, moderation | Tonio |
| `services/social` | 8004 | Follows, profiles, privacy, activity feed, movie nights, chat rooms | Joe · Maria |
| `services/intelligence` | 8005 | Recommendations, sentiment, weather, calendar | Joe |
| `services/notification` | 8006 | User notifications | Joe |
| `packages/common` | – | Shared technical code (`secret()`, JWT guard and middleware, later events and health) | everyone |

**Tasks rotate:** over the five sprints everyone works in every service, so the whole team understands the whole
system. Who does a task is on the board.

Infrastructure in Docker: one **PostgreSQL 16** per service (host ports 5433–5438), **RabbitMQ** (5672, UI 15672, via
`php-amqplib`; never Laravel queue jobs between services),
**Redis** (6379, cache and Gateway sessions, `predis` client, not for events). **Reverb** (8080) runs from the Gateway.
Auth: **JWT RS256**, signed by Identity, verified in every service. PHP 8.2+ on each laptop.

## Golden rules (always follow)

1. **A service never reads or writes another service's database.** Get other data through its API or through events.
2. **No foreign keys across services.** `user_id` = Identity `users.id`, `movie_id` = Catalog `movies.id` (never `tmdb_id`), stored as plain `uuid` columns.
3. **Validate inside each service**, even if the Gateway already validated (how: `docs/lumen.md`).
4. **Never return raw models.** Always use API Resources.
5. **Never hardcode or commit secrets.** Values live only in `secrets/`, read with `secret('name')` in config files. `.env` holds non-secret settings only.
6. **Contracts first.** Changing an endpoint or an event updates `docs/api/<service>.md` or `docs/events.md` in the same change, and the services that use it are told.
7. **Graceful fallback.** If a service is down, the Gateway shows `<x-service-unavailable/>` for that section. The page never crashes.
8. Code, commits and docs are in **English**.
9. **Don't repeat code.** Reusable logic goes in one place: a `Services/` class, trait or base class inside a service; `packages/common` across projects. Share **technical** code only, never business logic, models or database access.

## Where the rules are

| Before you… | Read |
|---|---|
| need the big picture (every service's role, tables, endpoints, events) | `docs/MoviePlatform-Service-Reference.docx` |
| write any service code | `docs/lumen.md` (structure, routes, validation, models, policies, tests) |
| add or change an endpoint | `docs/api/conventions.md` and `docs/api/<service>.md` |
| publish or consume an event | `docs/events.md` |
| write a migration or a model | `docs/database.md` and the service's ERD in `docs/erd/<service>-erd.drawio` |
| touch shared code | `packages/common/README.md` |
| set up or run the project | `README.md` |

## Running

Docker runs only the infrastructure (`docker compose up -d`). Services run with `php -S 127.0.0.1:800X -t public`, the
Gateway with `php artisan serve --port=8000`. Background processes (`events:consume`, `reverb:start`) start from each
project's `composer run dev` script, never as Docker containers. Details: `README.md`.

## Git workflow

- Branches are created from the Azure DevOps task, **based on `develop`**: `feature/<taskId>-short-name`, `fix/…`, `docs/…`, `chore/…`.
- Commit messages include `AB#<taskId>`. Pull requests target `develop` and end with `Fixes AB#<taskId>`.
- 1 approval from a member who owns a different service. Squash and merge (merge commit for chained branches), then delete the branch.
- **Never push directly to `main` or `develop`.** `main` is updated only at the end of a sprint.

## Definition of done (before opening a PR)

- [ ] The task's "Done when" criteria in Azure Boards are met
- [ ] Follows `docs/lumen.md` (or `gateway/CLAUDE.md`); inputs validated, ownership checked, no raw models returned
- [ ] Events match `docs/events.md`; handlers are idempotent
- [ ] `docs/api/<service>.md`, `docs/events.md` or the ERD updated if something changed
- [ ] Tests pass; no secrets, `.env`, `vendor/` or `node_modules/` committed

## Instructions for Claude

- Before coding, read the task (the user pastes it or gives the Azure task id) and the docs from "Where the rules are".
- Work **only in the service the task belongs to**, plus its Gateway pages. If another service needs a change, say so instead of editing it.
- **Never** add cross-service database queries or foreign keys, even as a "quick fix".
- Ask before changing an API contract, an event payload, `docker-compose.yml` or anything in `packages/common`.
- Before writing something, check whether it already exists in the service or in `packages/common` (rule 9).
- Follow the folder structure and naming, and match the existing code style of the service.
- Prefer small changes that fit one task and one pull request. Update the docs in the same change.
- Keep explanations short and suggest the commit message with `AB#<id>`.
