# Identity API

Port **8001**. Users, authentication, roles and JWT issuing. Follows `docs/api/conventions.md`.

## Authentication

Tokens are RS256 JWTs valid for **30 minutes** (`config('jwt.ttl')`, `JWT_TTL`, default 1800 seconds), with the claims
of `docs/api/conventions.md` section 7. Identity keeps no session: logging out only tells the Gateway to forget the
token, which then expires on its own.

A user, as returned by every endpoint below:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `name` | string | |
| `email` | string | always lowercase |
| `avatar` | string \| null | |
| `role` | string | `user` or `admin` |

### POST /api/register

Creates an account with email and password and logs it in. The email is saved in lowercase, and the role is always
`user` (`role` and `is_blocked` in the request are ignored).

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `name` | string | required, max 100 |
| `email` | string | required, valid email, max 255, not already used (in any letter case) |
| `password` | string | required, min 8 |
| `password_confirmation` | string | required, equal to `password` |

**Response** `201`, same body as login

```json
{"data": {"token": "eyJhbGciOiJSUzI1NiIs…", "expires_at": "2026-11-03T18:50:00Z", "user": {"id": "0193a7c0-1111-7aaa-8bbb-000000000001", "name": "Maria Makary", "email": "maria@example.com", "avatar": null, "role": "user"}}}
```

**Errors:** `422` invalid fields, or `email` already taken (`"The email has already been taken."`)

**Events:** publishes `UserRegistered` after the user is saved

### POST /api/login

Logs in with email and password. The email is compared in lowercase.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `email` | string | required, valid email, max 255 |
| `password` | string | required |

**Response** `200`

```json
{"data": {"token": "eyJhbGciOiJSUzI1NiIs…", "expires_at": "2026-11-03T18:50:00Z", "user": {"id": "0193a7c0-1111-7aaa-8bbb-000000000001", "name": "Maria Makary", "email": "maria@example.com", "avatar": null, "role": "user"}}}
```

**Errors:** `401` `"Invalid credentials."` (wrong password, unknown email, or an account created through social login
that has no password: the same answer, so nobody can tell which emails exist) · `403` `"Account blocked."` (only after
a correct password) · `422` invalid `email` or `password`

### POST /api/logout

Accepted so the Gateway has one call for logging out. Nothing is revoked: the Gateway deletes the token from its
session and the token expires within 30 minutes.

**Who:** user

**Response** `204`, no body

**Errors:** `401` no or invalid token

### POST /api/token/refresh

Exchanges a still valid token for a new one. The user is reloaded, so the new token carries the current `name` and
`role`. The Gateway calls it when its token expires in less than 10 minutes, and right after the user changes their
name.

**Who:** user

**Response** `200`, same body as login

**Errors:** `401` no, invalid or expired token, or the user was deleted · `403` `"Account blocked."`

### GET /api/me

The current user, read from the database with the token's `sub`.

**Who:** user

**Response** `200`

```json
{"data": {"id": "0193a7c0-1111-7aaa-8bbb-000000000001", "name": "Maria Makary", "email": "maria@example.com", "avatar": null, "role": "user"}}
```

**Errors:** `401` no, invalid or expired token, or the user was deleted · `403` `"Account blocked."`
