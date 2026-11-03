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

## Password reset

Every email field of this API (register, login, forgot, reset) refuses control characters such as line breaks, with
`422` on `email`.

### POST /api/password/forgot

Emails a reset link to the account, if it exists. The answer is always the same, so nobody can tell which emails
exist. The link goes to the Gateway page `<GATEWAY_URL>/reset-password?token=…&email=…` and works once, for
**60 minutes**. Asking again replaces the older link. Accounts created through social login get a link too.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `email` | string | required, valid email, max 255 |

**Response** `200`, also for an unknown email

```json
{"data": {"message": "If this email exists, we sent you a link."}}
```

**Errors:** `422` invalid `email`

### POST /api/password/reset

Sets a new password with the token from the link, then deletes the token. Works for social-login accounts too: it sets
their first password. Tokens already issued stay valid until they expire.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `email` | string | required, valid email, max 255 |
| `token` | string | required, from the link |
| `password` | string | required, min 8 |
| `password_confirmation` | string | required, equal to `password` |

**Response** `204`, no body

**Errors:** `422` on `token`: `"This password reset link is invalid or has expired."` (wrong, used or expired token, or
an unknown email) · `422` other invalid fields

## Users

What other services and pages may know about a user: built by `PublicUserResource`, **never** with the email or the
role. Blocked users are still returned by id, so their old content keeps a name.

A public user:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | Identity `users.id` |
| `name` | string | |
| `avatar` | string \| null | |
| `bio` | string \| null | |
| `created_at` | date | when the account was created |

### GET /api/users/{id}

One user.

**Who:** guest

**Response** `200`

```json
{"data": {"id": "0193a7c0-1111-7aaa-8bbb-000000000001", "name": "Maria Makary", "avatar": null, "bio": "Sci-fi first.", "created_at": "2026-10-30T18:20:00Z"}}
```

**Errors:** `404` `"User not found."` (unknown id) · `404` `"Not found."` (`id` is not a UUID)

### GET /api/users

Public users for a list of ids, in one call, for every page that shows authors (reviews, lists, the feed, followers).
Guests can call it, so authors show for guests too.

Results come **in the order of the ids**. Unknown ids are skipped (a user may have been deleted), so the result can be
shorter than the request. A repeated id is returned once. Not paginated.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `ids` | string | query, required, comma-separated Identity user ids, each a UUID, at most 100 |

`GET /api/users?ids=0193a7c0-2222-7aaa-8bbb-000000000002,0193a7c0-1111-7aaa-8bbb-000000000001`

**Response** `200`

```json
{
  "data": [
    {"id": "0193a7c0-2222-7aaa-8bbb-000000000002", "name": "Tonio Jreige", "avatar": null, "bio": null, "created_at": "2026-10-31T09:00:00Z"},
    {"id": "0193a7c0-1111-7aaa-8bbb-000000000001", "name": "Maria Makary", "avatar": null, "bio": "Sci-fi first.", "created_at": "2026-10-30T18:20:00Z"}
  ]
}
```

**Errors:** `422` `ids` missing or empty, more than 100 ids, or an id that is not a UUID (`errors.ids` or
`errors.ids.<position>`)

### GET /api/users/search

Finds people by name, to invite them or follow them. Matches any part of the name in any letter case; `%` and `_` are
plain characters. Blocked users are left out. At most **10** users, sorted by name. Not paginated.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `q` | string | query, required, 2 to 100 characters |

**Response** `200`, same items as `GET /api/users`

```json
{"data": [{"id": "0193a7c0-1111-7aaa-8bbb-000000000001", "name": "Maria Makary", "avatar": null, "bio": "Sci-fi first.", "created_at": "2026-10-30T18:20:00Z"}]}
```

**Errors:** `401` no or invalid token · `422` `q` missing, shorter than 2 or longer than 100 characters
