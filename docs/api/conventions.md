# API conventions

Rules every service API follows. Each service documents its own endpoints in `docs/api/<service>.md`
using these conventions, so the Gateway and the other services can call any API the same way.

## 1. Format

- **JSON only**, in both directions. Clients send `Accept: application/json` and, with a body,
  `Content-Type: application/json`. A service never returns HTML, including for errors.
- Field names are **snake_case**: `movie_id`, `created_at`, `is_blocked`.
- All routes start with `/api/`. Resources are plural nouns: `/api/movies`, `/api/movies/{id}/reviews`.

## 2. Ids

- **Every id is a UUID string**: `"0193a7c2-5f1e-7b3a-9c4d-2e8f6a1b0c3d"`.
- References to another service's data use that service's id: `user_id` = Identity `users.id`,
  `movie_id` = Catalog `movies.id` (never `tmdb_id`).

## 3. Dates

- **ISO-8601 in UTC with `Z`**: `"2026-11-03T18:20:00Z"`.
- In API Resources: `$this->created_at?->toIso8601ZuluString()`.
- Dates sent by clients are accepted in the same format and stored in UTC.

## 4. Success responses

Responses are built with **API Resources** (never raw models).

| Case | Status | Body |
|---|---|---|
| Read one | `200` | `{"data": { … }}` |
| Read a list | `200` | `{"data": [ … ], "meta": { … }}` (see Pagination) |
| Create | `201` | `{"data": { … }}`, the created resource |
| Update | `200` | `{"data": { … }}`, the updated resource |
| Delete | `204` | no body |

```json
{
  "data": {
    "id": "0193a7c2-5f1e-7b3a-9c4d-2e8f6a1b0c3d",
    "movie_id": "0193a7c1-0a2b-7c3d-8e4f-5a6b7c8d9e0f",
    "rating": 4,
    "created_at": "2026-11-03T18:20:00Z"
  }
}
```

## 5. Pagination

Every list endpoint is paginated.

- Query parameters: `?page=1&per_page=20`. Default `per_page` is 20, maximum 100.
- Response: exactly these three `meta` fields.

```json
{
  "data": [ … ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "total": 93
  }
}
```

## 6. Errors

Every error uses the same shape. `errors` is present only for validation errors.

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

| Status | When | `message` example |
|---|---|---|
| `401` | Missing, invalid or expired token | `"Unauthenticated."` |
| `403` | Authenticated but not allowed (not the owner, not an admin, blocked user) | `"This action is unauthorized."` |
| `404` | Resource does not exist (or must stay hidden from this user) | `"Movie not found."` |
| `422` | Validation failed, or a business rule is broken (e.g. already following this user) | `"The given data was invalid."` |
| `503` | A service or external API this request depends on is unavailable | `"Catalog service is unavailable."` |
| `500` | Unexpected error | `"Server error."` (never a stack trace or exception message) |

## 7. Authentication

### Users: JWT

- Header: `Authorization: Bearer <token>`. The Gateway sends the token stored in the user's session.
- Tokens are issued by Identity, signed with **RS256**, and verified by every service with Identity's public key.
- Claims:

| Claim | Content |
|---|---|
| `sub` | the user's UUID (Identity `users.id`) |
| `role` | `user` or `admin` |
| `name` | the user's display name |
| `exp` | expiry, 2 hours after issuing |

- Each endpoint in `docs/api/<service>.md` states who may call it: **guest** (no token), **user**, **admin**,
  or **service**.

### Services: service token

- Service-to-service calls use only `/api/internal/*` routes, with the header `X-Service-Token: <service_token>`.
- Internal routes never accept a user JWT, and public routes never accept the service token.

## 8. Headers

| Header | Direction | Rule |
|---|---|---|
| `Authorization: Bearer …` | request | User calls (see 7) |
| `X-Service-Token` | request | Internal calls only (see 7) |
| `X-Correlation-ID` | request and response | Every call carries one. If missing, the service creates a UUID. It is passed on to every outgoing call and published event, and returned in the response, so one user action can be followed across all service logs. |
| `Accept: application/json` | request | Always |

## 9. Health

Every service exposes `GET /api/health`, without authentication:

```json
{"status": "ok", "service": "catalog", "database": "ok", "rabbitmq": "ok"}
```

`200` when everything is ok, `503` with `"status": "degraded"` and the failing part set to `"fail"` otherwise.

## 10. Calling another service

- Only through a Client class (`app/Integration/Clients` in services, `app/Services/Clients` in the Gateway).
- 3-second timeout, 2 retries; a connection failure becomes a `ServiceUnavailable` exception.
- The Gateway shows `<x-service-unavailable/>` for that section instead of failing the whole page.
