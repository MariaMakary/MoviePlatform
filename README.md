# MoviePlatform

Movie discovery and social platform: a Laravel 12 Gateway (web UI) and 6 Lumen 10 microservices.
Architecture, conventions and rules: see [`CLAUDE.md`](CLAUDE.md).

**How it runs:** Docker runs only the infrastructure (one PostgreSQL per service, Redis, RabbitMQ).
The apps run on your PC: the services with `php -S`, the Gateway with `php artisan serve`.

| App | Folder | URL | Database (host port) |
|---|---|---|---|
| Gateway | `gateway/` | http://localhost:8000 | – |
| Identity | `services/identity/` | http://localhost:8001 | `identity-db` (5433) |
| Catalog | `services/catalog/` | http://localhost:8002 | `catalog-db` (5434) |
| Engagement | `services/engagement/` | http://localhost:8003 | `engagement-db` (5435) |
| Social | `services/social/` | http://localhost:8004 | `social-db` (5436) |
| Intelligence | `services/intelligence/` | http://localhost:8005 | `intelligence-db` (5437) |
| Notification | `services/notification/` | http://localhost:8006 | `notification-db` (5438) |

Redis: `localhost:6379` · RabbitMQ: `localhost:5672`, management UI http://localhost:15672

## Requirements

- Docker Desktop
- PHP 8.2+ with `extension=pdo_pgsql` and `extension=sockets` enabled in `php.ini` (XAMPP: remove the `;` in front of both)
- Composer
- Node.js (for the Gateway's CSS/JS)

## First-time setup

Run everything from the repo root in PowerShell.

**1. Secrets.** Real values live in `secrets/`, which is git-ignored. `secrets.example/` lists the files you need.
Get the values from the team privately (never in the repo, never in chat channels that are saved), or, for a purely
local setup, generate your own:

```powershell
New-Item -ItemType Directory -Force secrets | Out-Null; Get-ChildItem secrets.example -File | Where-Object { $_.Name -ne 'jwt_private_key' -and (-not (Test-Path "secrets/$($_.Name)") -or (Get-Item "secrets/$($_.Name)").Length -eq 0) } | ForEach-Object { [IO.File]::WriteAllText("$PWD\secrets\$($_.Name)", -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 24 | ForEach-Object {[char]$_})) }
```

It fills every missing or empty file (except `jwt_private_key`) and never overwrites a value. Don't edit these files
in an editor: a trailing line break becomes part of the database password. Each file holds one value on one line. Choose them before the first `docker compose up`: the databases and RabbitMQ
read them only on their very first start.

**JWT private key.** `secrets/jwt_private_key` is not generated: it must match the public key committed in
`keys/jwt_public.pem`, which every service uses to verify tokens. Get the file from the team privately and save it as
`secrets/jwt_private_key`. Never generate your own: tokens signed with another key are rejected everywhere.

Only if the private key ever leaks, one person creates a new pair, commits the new `keys/jwt_public.pem` and shares the
new private key with the team (`openssl` comes with Git for Windows):

```powershell
& "C:\Program Files\Git\usr\bin\openssl.exe" genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out secrets/jwt_private_key
& "C:\Program Files\Git\usr\bin\openssl.exe" pkey -in secrets/jwt_private_key -pubout -out keys/jwt_public.pem
```

**2. PHP and JS dependencies.**

```powershell
composer install          # installs packages/common, the Gateway and every service
cd gateway; npm install; cd ..
```

**3. `.env` for each app.**

```powershell
Copy-Item gateway/.env.example gateway/.env; php gateway/artisan key:generate
foreach ($s in "identity","catalog","engagement","social","intelligence","notification") { (Get-Content "services/$s/.env.example") -replace '^APP_KEY=$', ('APP_KEY=' + -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 32 | ForEach-Object {[char]$_})) | Set-Content "services/$s/.env" }
```

Lumen has no `key:generate`, so the second line writes a random 32-character `APP_KEY` into each service's `.env`.

No passwords go in `.env`: the apps read them from `secrets/` (`SECRETS_DIR` in `.env` points there), the same
files the database and RabbitMQ containers use.

**4. Start the infrastructure and run the migrations.**

```powershell
docker compose up -d
docker compose ps            # wait until everything shows "healthy"
cd services/identity; php artisan migrate; cd ../..
# …same for the other services
```

## Every day

```powershell
docker compose up -d                       # infrastructure
cd services/identity; php -S 127.0.0.1:8001 -t public
```

Start each service you need in its own terminal with its port from the table above (Lumen has no `artisan serve`).
For the Gateway run `php artisan serve --port=8000` in `gateway/`, plus `npm run dev` for CSS/JS with hot reload.
Check a service with `http://localhost:8001/api/health`: `200` when its database and RabbitMQ are reachable, `503`
with the failing part set to `"fail"` otherwise.

You don't need every database: `docker compose up -d identity-db redis rabbitmq` starts only what Identity needs.

## Useful commands

```powershell
docker compose ps                          # status of the containers
docker compose logs -f identity-db         # logs of one container
docker compose stop                        # stop the infrastructure (data is kept)
docker compose down                        # remove the containers (data is kept in volumes)
docker compose down -v                     # remove the containers AND all data (fresh start)
```

Inside a service folder: `php artisan migrate`, `vendor/bin/phpunit` (tests). The Gateway has no automated tests: its pages are checked by hand.
