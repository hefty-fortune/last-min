# Local development (Docker)

This project runs as a plain PHP 8.3 backend with PostgreSQL for local development.

## 1) Prepare environment

```bash
cp .env.example .env
```

## 2) Start containers

```bash
docker compose up --build
```

`backend` depends on a healthy `postgres` service and starts only after Postgres is ready.
The backend startup runs migrations automatically (`php bin/migrate.php`) before launching the HTTP server.

## 3) Backend base URL

Backend is exposed at:

- `http://localhost:8080`

Quick check:

```bash
curl -i http://localhost:8080/api/v1/me
```

> Expected without auth: `401` JSON response.

## 4) Local auth and authorized requests

This backend does **not** provide local login, password auth, session auth, or cookie auth.
Protected routes expect one of these two inputs:

1. trusted upstream actor headers
2. bearer API key that resolves into actor context

### Option A: manual upstream actor headers

The request resolver accepts these headers:

- `X-Actor-Id`
- `X-Actor-Subject`
- `X-Actor-Roles`
- `X-User-Profile-Id` (optional for `/me`, required by some provider/client flows)

Minimal authorized `/me` example:

```bash
curl -sS http://localhost:8080/api/v1/me \
  -H 'X-Actor-Id: local-actor-1' \
  -H 'X-Actor-Subject: sso|local-user-1' \
  -H 'X-Actor-Roles: client' | jq
```

Example with profile linkage:

```bash
curl -sS http://localhost:8080/api/v1/me \
  -H 'X-Actor-Id: local-actor-1' \
  -H 'X-Actor-Subject: sso|local-user-1' \
  -H 'X-Actor-Roles: provider' \
  -H 'X-User-Profile-Id: profile-local-1' | jq
```

Use this mode for provider/client flow testing because current bearer API keys do not include `profile_id`.

### Option B: local admin bearer token

For admin routes, bootstrap a local token:

```bash
docker compose exec backend php bin/dev-bootstrap-admin-api-key.php \
  --dsn="pgsql:host=postgres;port=5432;dbname=lastmin" \
  --db-user="lastmin" \
  --db-pass="lastmin" \
  --actor-id="local-dev-admin" \
  --role="admin" \
  --name="Local FE admin token"
```

Then call protected admin endpoints:

```bash
curl -sS http://localhost:8080/api/v1/admin/organizations \
  -H "Authorization: Bearer <token>" | jq
```

### What `AUTH_IDENTITY_NOT_LINKED` means

This error means the backend could not build actor context from the request.
Concretely, neither of these was present:

- both `X-Actor-Subject` and `X-Actor-Id`
- nor a valid `Authorization: Bearer <token>` API key

The backend is behaving correctly in local development when `/api/v1/me` returns this response without auth headers.

### Do you need DB seed data for auth?

For `/api/v1/me`: no.

For admin route testing with bearer token: no extra seed is needed beyond creating the token.

For provider/client business flows:

- you usually need domain rows such as provider, offering, or opening depending on the route
- current local header auth is trust-based, so some flows can work with a synthetic `X-User-Profile-Id`
- for realistic testing, create the surrounding business data first

### Are there login/register routes?

No local login/register routes are currently wired.
The app assumes authentication comes from an upstream SSO or trusted edge and this backend consumes the resolved identity.

### Are there auth env vars in `.env`?

No local auth env vars are required in the current setup.
`.env.example` contains DB settings and `STRIPE_WEBHOOK_SECRET`, but no separate auth-mode toggle.

## 5) Manual migration command (if needed)

```bash
docker compose run --rm backend php bin/migrate.php
```

## 6) Frontend integration target

Frontend should target this backend API base URL:

- `http://localhost:8080/api/v1`

If the frontend is running outside Docker, point its API base URL directly at that origin and send either:

- manual `X-Actor-*` headers during local development, or
- `Authorization: Bearer <token>` for admin screens

Important: the current backend does not include explicit CORS handling.
If your frontend dev server runs on a different origin such as `http://localhost:3000`, the simplest setup is a dev proxy that forwards `/api/*` to `http://localhost:8080`.

There is currently no frontend service enabled in `docker-compose.yml`; it is intentionally left commented as a placeholder.

## 7) Inspect running Postgres (optional)

```bash
docker compose exec postgres psql -U "$DB_USER" -d "$DB_NAME"
```

## Assumptions / limitations

- Local DB uses Postgres container service name `postgres` and DB env vars (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`).
- `DB_DSN` is optional; when empty, runtime composes a pgsql DSN from the `DB_*` values.
- Postgres data persists in `postgres_data` Docker volume.
- Stripe integration is stubbed in code; `STRIPE_WEBHOOK_SECRET` is used by webhook signature verification.
- Frontend service is intentionally left commented in `docker-compose.yml` as a placeholder TODO.
