# Database conventions

Rules for every service's database, migrations and models.

## One database per service

- PostgreSQL 16, **one container per service** (`identity-db`, `catalog-db`, …), each holding only that service's
  database and user (`identity_db` / `identity_user`, …). The password lives in `secrets/<service>_db_password`.
- The apps run on your PC and reach their database at `127.0.0.1`: identity 5433, catalog 5434, engagement 5435,
  social 5436, intelligence 5437, notification 5438.
- **A service never reads or writes another service's database.** Get other data through that service's API or through
  events (CLAUDE.md, golden rule 1).

## Tables and keys

- **UUID primary keys everywhere**: `$table->uuid('id')->primary()` and the `HasUuids` trait on the model
  (time-ordered UUIDs).
- Inside a service: `$table->foreignUuid('user_id')->constrained()`.
- References to another service's data: a plain `$table->uuid('movie_id')`, **never a foreign key**. `user_id` always
  means Identity `users.id`, `movie_id` always means Catalog `movies.id` (never `tmdb_id`).
- Plural snake_case table names, `created_at` / `updated_at` on every table.
- Flexible data in `jsonb` (`$table->jsonb('settings')`). Case-insensitive search with `ILIKE`.

## Changing the schema

- Every schema change is a migration: `php artisan make:migration create_reviews_table`, then `php artisan migrate`.
- Never edit a migration that is already merged into `develop`; add a new one.
- The service's ERD in `docs/erd/` is updated in the **same PR** as the migration.
