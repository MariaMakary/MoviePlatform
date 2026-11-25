# Security checks

What protects MoviePlatform, how each part is checked, and the results of the last reviews (task 1287, 2026-11-18;
authorization, task 1286, 2026-11-19; input validation, task 1285, 2026-11-25). Update the results table in the PR that changes one of these areas.

## Results

| Check | How | Result |
|---|---|---|
| No secret in the code today | `bash docker/check-secrets.sh` (CI job `secrets`) | Passes |
| No secret anywhere in the git history | gitleaks on every commit (CI job `secrets`, command below) | No leaks in 157 commits (2026-11-18): see [gitleaks](#gitleaks) |
| No `.env`, `secrets/` file or filled `secrets.example/` file ever committed | `git log --all` over the whole history | None, in all 313 commits |
| Blade never prints user input unescaped | Every Gateway view searched for `{!! !!}` | None: every view uses `{{ }}` |
| Avatar uploads are images of at most 2 MB | Validation rules and tests in Identity and the Gateway | JPG or PNG only, at most 2048 KB |
| Auth endpoints and APIs are rate limited | Tests in Identity (`RateLimitTest`) and common | 429 + `Retry-After` past the limits below |
| Every endpoint validates its own input | Each endpoint's rules read against `routes/api.php` and `docs/api/` (2026-11-25): see [Input validation](#input-validation) | 106/106 checked; 4 missing rules fixed |
| Every authorization rule refuses the wrong user | Each rule tried on the running system with a wrong user, a guest and a forged token (2026-11-19): see [Authorization](#authorization) | 96/96 refused or allowed as expected; no failure |

## Secrets

Real values live only in `secrets/` (git-ignored) and are read with `secret('name')`. `secrets.example/` lists the
names with empty files. Two checks run on every pull request, in the `secrets` CI job:

- **`docker/check-secrets.sh`** looks at the code as it is now. It fails when a file under `secrets/` is committed, a
  file in `secrets.example/` is not empty, the code reads a `secret('name')` that `secrets.example/` does not list, a
  `.env.example` has a value for `APP_KEY` or a `…PASSWORD`, `…SECRET`, `…TOKEN`, `…API_KEY` or `…PRIVATE_KEY` key,
  or a private key is committed outside `tests/Fixtures`. It prints names only.
- **gitleaks** looks at every commit, so it also finds a secret that was committed and deleted later (still readable
  in the history). It uses the default rules with `.gitleaks.toml`, which only allows two things that are not
  secrets: the RSA keys in `tests/Fixtures/jwt` (made for the tests, trusted nowhere else) and `REVERB_APP_KEY`
  (Reverb's public app key: the Gateway sends it to every browser so Echo can connect; the secret part is
  `reverb_app_secret`, only in `secrets/`).

### gitleaks

Run it locally, from the repository root (Docker must be running; `--redact` keeps the values out of the output):

```powershell
docker run --rm -v "${PWD}:/repo" --entrypoint sh ghcr.io/gitleaks/gitleaks:v8.21.2 -c "git config --global --add safe.directory /repo && gitleaks detect --source /repo --config /repo/.gitleaks.toml --redact --no-banner -v"
```

`no leaks found` means the history is clean. For a finding: if it is a real secret, **rotate it first** (a secret in
the history is public even after the file is deleted), then tell the team; if it is not a secret, add it to the
allowlist in `.gitleaks.toml` with a comment saying why.

Last run: 2026-11-18, 157 commits of `feature/1287-rate-limiting-secrets` (all of `develop` included). The first run
found 3 hits, all `REVERB_APP_KEY` in `gateway/.env.example`, `services/notification/.env.example` and
`services/social/.env.example` (rule `generic-api-key`): Reverb's public app key, not a secret, so it was added to the
allowlist instead of being rotated. Second run with that allowlist: **no leaks found**.

## Output escaping (XSS)

The Gateway is the only app that renders HTML. Every Blade view prints with `{{ }}`, which escapes HTML, and no view
uses `{!! !!}`. The only other output helper is `@js()` in `components/modal.blade.php`, which encodes its value as
JSON. Keep it that way: never `{!! !!}` with anything that comes from a user or a service (names, bios, reviews,
messages, list titles).

## Avatar uploads

Checked twice, with the same rule `required|file|mimes:jpg,png|max:2048`:

- the Gateway form (`App\Livewire\Auth\Profile::AVATAR_RULES`), before sending anything;
- Identity (`App\Http\Requests\UpdateAvatarRequest`, `MAX_KILOBYTES = 2048`), which decides.

`mimes` checks the file's content, not its name: `ProfileTest` refuses a GIF, a GIF renamed to `.png`, and a 2049 KB
PNG. Files are saved under a new UUID name, never the uploaded one.

## Rate limits

| Where | What | Limit | Counted per |
|---|---|---|---|
| Every service API route | `throttle` (`api`), from `packages/common` | 60 per minute | JWT `sub` (requests without a token are not limited here) |
| Identity `POST /api/login` | `throttle:login` | 5 per minute, cleared by a successful login | email |
| Identity `POST /api/register` | `throttle:register` | 5 per 10 minutes | email |
| Identity `POST /api/password/forgot` | `throttle:password_forgot` | 3 per 10 minutes | email |
| Identity `POST /api/password/reset` | `throttle:password_reset` | 5 per 10 minutes, cleared on success | email |
| Gateway, every page and Livewire update | `throttle:web` | 300 per minute | logged-in user, else IP |
| Gateway login form | `ThrottlesByIp` | 10 per minute, cleared by a successful login | IP |
| Gateway register / forgot / reset forms | `ThrottlesByIp` | 30 / 10 / 10 per 10 minutes | IP |

The services only see the Gateway's IP, so they count per email or per user and the per-IP limits are in the Gateway.
The Gateway limits are high on purpose: a class on one university Wi-Fi shares one IP. Counters are kept in Redis
(`CACHE_DRIVER=redis`), shared by every process of a service.

## Authorization

Every service decides for itself who may do what, from the JWT (`jwt` middleware: RS256, signature checked with
`keys/jwt_public.pem`); the Gateway only hides pages. Rules and where they are enforced:

| Rule | Enforced by |
|---|---|
| Only the author edits or deletes a review; only the owner changes a list or its movies | Engagement `ReviewPolicy` / `ListPolicy` (`403`) |
| A private list is visible only to its owner | Engagement `ListService::findVisible` (`404`, like an unknown id, so nobody learns it exists); `publicListsOf` and the public search leave it out |
| Only participants see a movie night or act on it; only the host invites, finalizes or cancels | Social `MovieNight::withParticipant` (`404` for others), host checks in `ParticipantService` / `MovieNightService` (`403`); removing a candidate needs who added it or the host (`403`) |
| A user reads and marks only their own notifications | Notification `Notification::ownedBy` (`404` for another user's) |
| Recommendations: only the user themselves or an admin | Intelligence gate `view-recommendations` (`403`) |
| Admin endpoints need role `admin` | `jwt` + `admin` middleware of `packages/common` (`401` without a valid token, `403` for a normal user) |
| `/api/internal/*` needs the service token | `service` middleware (`X-Service-Token` = `secret('service_token')`, `401` otherwise; a user's JWT is not enough) |
| Admin pages need an admin session | Gateway `/admin` group with `auth.jwt` + `admin.page` (guest → `/login`, normal user → `403`); both are Livewire persistent middleware, so they also guard the pages' actions |
| Private channels | Gateway `routes/channels.php` (`broadcast` guard): `user.{id}` only that user, `movie-night.{id}` only participants (asked to Social with the user's token), `room.{id}` any logged-in user (rooms are public); guests never |

### How it was checked

A script logged in three throwaway members (an owner, another user, an outsider) and the admin, gave the owner a
review, a private list, a movie night (the other user invited and accepted) and a notification, then tried every rule
on the running system (all six services and the Gateway, `develop` of 2026-11-19) with the wrong user, a guest and
forged tokens, plus one allowed call per rule as a control. Gateway checks used real browser sessions (Livewire login,
then pages and `POST /broadcasting/auth`). **96 of 96 answered as expected.**

| Area | Tried | Answer |
|---|---|---|
| Engagement | Another user edits or deletes the review; changes, deletes, adds to or removes from the list | `403` |
| Engagement | Another user or a guest opens the private list; it is missing from the owner's public lists, from "my lists" of another user and from the public search | `404` / absent |
| Social | Non-participant opens, RSVPs, adds a candidate or votes; it is missing from their list of nights | `404` / absent |
| Social | Participant (not host) invites, finalizes or cancels; removes a candidate someone else added | `403` |
| Notification | Another user marks the notification read; it is missing from their list | `404` / absent |
| Intelligence | Another user reads the recommendations (the user and an admin can) | `403` |
| Admin endpoints | Engagement `/stats` and `/admin/reviews` (4), Catalog `/admin/movies` (4), Intelligence `/sentiment/analyze`, as a normal user and as a guest | `403` / `401`; the movie was still there after the refused delete |
| Forged tokens | An admin token signed with another key, an `alg: none` token, a user's token with `role` changed to `admin` | `401` on every service tried |
| Internal API | Catalog's four `/api/internal/*` routes without the token, with a wrong token, with a user's JWT | `401`; the right token gets `200` |
| Gateway pages | `/admin`, `/admin/movies` as guest and as a normal user; `/feed`, `/lists`, `/movie-nights`, `/notifications`, `/profile` as guest; another user's private list and movie night | `302` to `/login` / `403` / `404` |
| Gateway channels | `user.{id}` of someone else, `movie-night.{id}` as a non-participant, any channel as a guest, `room.{not a uuid}` | `403` |

Admin endpoints that were not on `develop` yet are covered by their own tests: Identity `/api/admin/users` (1271,
`AdminUserApiTest`), Social `/api/stats` (1267, `StatsApiTest`) and the Gateway's `/admin/reviews` and `/admin/users`
pages (1189, 1273). Run the same checks again after they are merged.

### Known limits

- **A token stays valid until it expires (at most 30 minutes).** Services only check the signature, so a user who is
  blocked or deleted keeps access to the other services until then; Identity refuses them at once (`/api/me`, login,
  refresh) and the Gateway logs them out at its next token refresh. Accepted for this project: closing it would need a
  token deny-list every service checks.
- `room.{id}` lets any logged-in user listen to any room: rooms are public on purpose (one per movie).

## Input validation

Every endpoint of every service checks its own input (`docs/lumen.md`, section 4), even when the Gateway checked it
first: a request class with static `rules()` and `$this->validate()`, which answers `422` with the field errors.
Reviewed endpoint by endpoint on 2026-11-25 (task 1285), from each `routes/api.php` and `docs/api/<service>.md`:
**106 endpoints, all checked**. The rules below are the ones in the code; the limits match the column sizes in the
migrations.

Applies everywhere, so it is not repeated in the tables:

- **Ids in the path.** `{id}` is either limited to a UUID by the `$uuid` pattern in `routes/api.php` (anything else:
  `404`, the route does not match), or checked in the code: `Str::isUuid()` before the query (`404`) or a
  `uuid` rule (`422`). A non-UUID never reaches Postgres, where it would be a `500`.
- **Pagination.** Every list reads `?page` and `?per_page` through `PaginatedCollection` (`packages/common`): a value
  that is not an integer falls back to the default, and `per_page` is kept between 1 and 100. It never fails.
- **Endpoints with no input** (health, `GET /me`, logout, refresh, stats, feed, watchlist, history and notification
  lists, `read-all`): only the token, from the `jwt` middleware.
- **Free text** gets `string` and a `max` (an array or a number is refused), and `required` refuses a blank string.
  Emails and URLs also refuse control characters (`not_regex:/[\x00-\x1F\x7F]/`): Laravel's `email` and `url` rules
  let a line break through.
- **Comma-separated id lists** (`?ids=a,b`) are split, trimmed and de-duplicated, then validated as an array of at
  most 100 UUIDs.

### Identity

| Endpoint | Validated in the service |
|---|---|
| `POST /register` | `name` string ≤ 100; `email` email ≤ 255, no control characters, not taken (any case); `password` 8–72, confirmed |
| `POST /login` | `email` as above (not the "taken" check); `password` string ≤ 255 |
| `POST /password/forgot` | `email` as above |
| `POST /password/reset` | `email`; `token` string ≤ 255; `password` 8–72, confirmed |
| `GET /users?ids=` | 1–100 UUIDs |
| `GET /users/{id}` | `$uuid` route |
| `GET /users/search` | `q` string 2–100 |
| `PUT /me` | `name` string ≤ 100; `bio` nullable string ≤ 500 |
| `POST /me/avatar` | `avatar` file, JPG or PNG by content, ≤ 2048 KB |
| `PUT /me/password` | `current_password` nullable string ≤ 255; `password` 8–72, confirmed |
| `PUT /me/genres` | `genre_ids` present array ≤ 20, each a UUID, no repeats |
| `GET /admin/users` | `q` nullable string ≤ 100 |
| `PATCH /admin/users/{id}/block`, `/unblock`, `DELETE /admin/users/{id}` | `$uuid` route |
| `GET /health`, `POST /logout`, `POST /token/refresh`, `GET /me`, `GET /stats` | no input |

### Catalog

| Endpoint | Validated in the service |
|---|---|
| `GET /movies?ids=` | 1–100 UUIDs |
| `GET /movies/popular`, `/movies/upcoming` | `page` integer 1–`MovieService::MAX_PAGE` |
| `GET /movies/search` | `q` string 2–100; `genre` UUID; `year` integer 1870–2100; `sort` one of `MovieService::SEARCH_SORTS`; `page` as above |
| `GET /movies/{id}`, `/movies/{id}/similar`, `GET /people/{id}` | `$uuid` route |
| `GET /genres` | no input |
| `GET /genres/{id}/movies` | `$uuid` route; `sort` `popularity` or `vote_average`; `page` as above |
| `GET /internal/movies?ids=` | 1–100 UUIDs (and the service token) |
| `GET /internal/discover` | `genres` ≤ 10 names ≤ 50, or `genre_ids` ≤ 10 UUIDs (one of the two); `exclude_ids` ≤ 100 UUIDs; `limit` integer 1–50 |
| `GET /internal/movies/{id}/features`, `/internal/movies/{id}/similar` | `$uuid` route |
| `GET /admin/movies` | `q` nullable string ≤ 100; `page` as above |
| `POST /admin/movies/import` | `tmdb_id` integer 1–2147483647 |
| `PUT /admin/movies/{id}` | `$uuid` route; `title` string ≤ 255; `overview` nullable ≤ 5000; `poster_path` nullable, a TMDB path (`/abc.jpg`) ≤ 255; `release_date` `Y-m-d`; `runtime` integer 1–1000; `genre_ids` array ≤ 20, existing genre UUIDs, no repeats |
| `DELETE /admin/movies/{id}` | `$uuid` route |
| `POST /admin/genre-counts` | `movie_counts` object ≤ 1000 entries, every key a UUID, every value an integer ≥ 0 |
| `GET /health` | no input |

### Engagement

| Endpoint | Validated in the service |
|---|---|
| `GET /movies/{id}/rating-summary`, `/movies/{id}/reviews`, `GET /users/{id}/reviews` | `id` UUID rule (`422`) |
| `GET /lists/public` | `q` string ≤ 150 |
| `GET /lists/{id}`, `GET /users/{id}/lists` | `$uuid` route |
| `POST /ratings` | `movie_id` UUID; `score` integer 1–5 |
| `POST /reviews` | `movie_id` UUID; `body` 10–2000 characters, not blank |
| `PUT /reviews/{id}`, `DELETE /reviews/{id}` | `id` checked with `Str::isUuid` (`404`); `body` as above |
| `GET /lists` | `movie_id` UUID |
| `POST /lists` | `title` string ≤ 150; `description` nullable ≤ 500; `is_public` boolean |
| `PUT /lists/{id}` | `$uuid` route; same fields, each optional |
| `DELETE /lists/{id}` | `$uuid` route |
| `POST /lists/{id}/movies` | `$uuid` route; `movie_id` UUID |
| `DELETE /lists/{id}/movies/{movieId}` | `$uuid` route; `movieId` UUID rule |
| `GET /watchlist/contains`, `GET /history/contains` | 1–100 UUIDs |
| `POST /watchlist` | `movie_id` UUID |
| `DELETE /watchlist/{movieId}` | `movieId` UUID rule |
| `POST /history` | `movie_id` UUID; `watched_at` nullable date, not in the future |
| `GET /admin/reviews` | `sentiment` a `SentimentLabel` case; `movie_id` UUID; `hidden` `true`/`false`/`1`/`0` |
| `PATCH /admin/reviews/{id}/hide`, `/unhide`, `DELETE /admin/reviews/{id}` | `id` checked with `Str::isUuid` (`404`) |
| `GET /health`, `GET /watchlist`, `GET /history`, `GET /stats` | no input |

### Social

| Endpoint | Validated in the service |
|---|---|
| `GET /users/{id}/followers`, `/following`, `/follow-stats`, `/privacy`, `/activity` | `id` UUID rule (`validUserId`, `422`) |
| `POST /follow/{userId}`, `DELETE /follow/{userId}` | `userId` UUID rule |
| `GET /follow/status` | 1–100 UUIDs |
| `PUT /privacy` | the four settings, each required boolean |
| `POST /movie-nights` | `title` string ≤ 100; `description` nullable ≤ 2000; `scheduled_at` date in the future; `location` ≤ 255 or `meeting_link` `http`/`https` URL ≤ 255 without control characters (at least one) |
| `GET /movie-nights/{id}`, `POST …/cancel` | `$uuid` route |
| `POST /movie-nights/{id}/invites` | `$uuid` route; `user_ids` array of 1–20 UUIDs, no repeats |
| `PATCH /movie-nights/{id}/rsvp` | `$uuid` route; `status` `accepted` or `declined` (`ParticipantStatus`) |
| `POST /movie-nights/{id}/candidates` | `$uuid` route; `movie_id` UUID |
| `DELETE /movie-nights/{id}/candidates/{candidateId}` | `$uuid` route for both |
| `POST /movie-nights/{id}/votes` | `$uuid` route; `candidate_id` UUID |
| `POST /movie-nights/{id}/finalize` | `$uuid` route; `candidate_id` nullable UUID |
| `GET /rooms/by-movie/{movieId}` | `$uuid` route |
| `GET /rooms/{id}/messages` | `$uuid` route; `before` UUID |
| `POST /rooms/{id}/messages` | `$uuid` route; `body` string ≤ 1000 |
| `GET /health`, `GET /feed`, `GET /privacy`, `GET /movie-nights`, `GET /rooms`, `GET /stats` | no input |

### Intelligence

| Endpoint | Validated in the service |
|---|---|
| `GET /suggestions/weather` | `lat` number −90–90; `lon` number −180–180 |
| `POST /calendar/ics`, `GET /calendar/google-link` | `title` string ≤ 255; `description` nullable ≤ 5000; `start` date; `end` date ≥ `start`; `all_day` boolean; `location` ≤ 255; `url` `http`/`https` URL ≤ 2048 without control characters |
| `GET /recommendations/{userId}` | `$uuid` route |
| `POST /sentiment/analyze` | `text` string ≤ 5000, not blank |
| `GET /health` | no input |

### Notification

| Endpoint | Validated in the service |
|---|---|
| `PATCH /notifications/{id}/read` | `id` checked with `Str::isUuid` (`404`) |
| `GET /health`, `GET /notifications`, `GET /notifications/unread-count`, `PATCH /notifications/read-all` | no input |

### Fixed in this review

| Service | Missing rule | Fix |
|---|---|---|
| Identity | No upper length on a new password: bcrypt reads only the first 72 bytes, so the rest was ignored without telling the user | `PasswordRules::NEW` (8–72, confirmed) on register, reset and change; `login.password` and `current_password` ≤ 255 |
| Intelligence | Calendar `url` accepted any scheme (`ftp:`…) and a trailing line break, and the ICS `URL:` line is not escaped | `http`/`https` only, no control characters |
| Social | `meeting_link` accepted a trailing line break | No control characters |
| Catalog | Admin edit `genre_ids` had no size limit (one `exists` query per id) | At most 20 (`UpdateMovieRequest::MAX_GENRES`) |

Also removed: a second, duplicate `/internal/discover` route group in Catalog's `routes/api.php`.

### Known limits

- `max:72` counts characters, bcrypt counts bytes: a 72-character password with accented letters or emoji can still be
  longer than 72 bytes and be cut. Accepted: it only happens to very long non-ASCII passwords.
