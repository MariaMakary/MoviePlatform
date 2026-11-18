# Security checks

What protects MoviePlatform, how each part is checked, and the results of the last review (task 1287,
2026-11-18). Update the results table in the PR that changes one of these areas.

## Results

| Check | How | Result |
|---|---|---|
| No secret in the code today | `bash docker/check-secrets.sh` (CI job `secrets`) | Passes |
| No secret anywhere in the git history | gitleaks on every commit (CI job `secrets`, command below) | No leaks in 157 commits (2026-11-18): see [gitleaks](#gitleaks) |
| No `.env`, `secrets/` file or filled `secrets.example/` file ever committed | `git log --all` over the whole history | None, in all 313 commits |
| Blade never prints user input unescaped | Every Gateway view searched for `{!! !!}` | None: every view uses `{{ }}` |
| Avatar uploads are images of at most 2 MB | Validation rules and tests in Identity and the Gateway | JPG or PNG only, at most 2048 KB |
| Auth endpoints and APIs are rate limited | Tests in Identity (`RateLimitTest`) and common | 429 + `Retry-After` past the limits below |

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
