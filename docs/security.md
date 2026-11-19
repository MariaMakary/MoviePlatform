# Security checks

What protects MoviePlatform, how each part is checked, and the results of the last reviews (task 1287, 2026-11-18;
authorization, task 1286, 2026-11-19). Update the results table in the PR that changes one of these areas.

## Results

| Check | How | Result |
|---|---|---|
| No secret in the code today | `bash docker/check-secrets.sh` (CI job `secrets`) | Passes |
| No secret anywhere in the git history | gitleaks on every commit (CI job `secrets`, command below) | No leaks in 157 commits (2026-11-18): see [gitleaks](#gitleaks) |
| No `.env`, `secrets/` file or filled `secrets.example/` file ever committed | `git log --all` over the whole history | None, in all 313 commits |
| Blade never prints user input unescaped | Every Gateway view searched for `{!! !!}` | None: every view uses `{{ }}` |
| Avatar uploads are images of at most 2 MB | Validation rules and tests in Identity and the Gateway | JPG or PNG only, at most 2048 KB |
| Auth endpoints and APIs are rate limited | Tests in Identity (`RateLimitTest`) and common | 429 + `Retry-After` past the limits below |
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
