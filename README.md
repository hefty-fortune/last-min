# U zadnji čas backend (local development quickstart)

This repository is a PHP 8 modular-monolith backend skeleton for **U zadnji čas**.  
It currently includes a runnable API with PostgreSQL support, admin setup endpoints, API key auth, idempotent booking/payment flows, and Stripe webhook deduplication.

Use this README as the **canonical starting point** for local setup and smoke testing.

---

## What is currently implemented

### Infrastructure and platform
- PHP backend served from `public/index.php`.
- PostgreSQL local stack via Docker Compose.
- SQL migrations via `bin/migrate.php`.
- Actor context auth via headers or bearer API keys.
- Idempotency handling for key write flows.
- Stripe webhook signature verification + deduplication storage.

### API routes currently wired
- Admin setup:
  - `POST /api/v1/admin/organizations`
  - `GET /api/v1/admin/organizations`
  - `GET /api/v1/admin/organizations/{organization_id}`
  - `POST /api/v1/admin/providers`
  - `GET /api/v1/admin/providers`
  - `GET /api/v1/admin/providers/{provider_id}`
  - `POST /api/v1/admin/users`
  - `GET /api/v1/admin/users`
  - `GET /api/v1/admin/users/{user_id}`
- Identity/API keys:
  - `POST /api/v1/api-key`
  - `GET /api/v1/api-keys`
  - `DELETE /api/v1/api-key/{api_key_id}`
  - `GET /api/v1/me`
- Milestone domain routes:
  - `POST /api/v1/providers`
  - `POST /api/v1/providers/{provider_id}/openings`
  - `POST /api/v1/bookings`
  - `POST /api/v1/bookings/{booking_id}/payments/initiate`
- Stripe webhook ingest:
  - `POST /api/v1/webhooks/stripe`

> This README only gives smoke-test examples for the endpoints requested in this task (admin setup + API key management).

---

## Prerequisites

Install these before first run:
- Docker + Docker Compose plugin (`docker compose` command)
- `curl`
- `jq` (used in examples for extracting IDs)
- PHP 8.1+ and Composer (for local dependency install)

---

## First-time setup

From the repo root:

```bash
cd /path/to/last-min
```

### 1) Install PHP dependencies

```bash
composer install
```

### 2) Create local env file

```bash
cp .env.example .env
```

### 3) Start Docker services (backend + PostgreSQL)

```bash
docker compose up --build -d
```

The backend container starts with:
- migration run (`php bin/migrate.php`)
- PHP built-in web server on port `8080`

### 4) Run migration command manually (safe to re-run)

```bash
docker compose run --rm backend php bin/migrate.php
```

### 5) Base URLs

- Backend base URL: `http://localhost:8080`
- Frontend API base URL: `http://localhost:8080/api/v1`

Quick unauthenticated check (expected `401`):

```bash
curl -i http://localhost:8080/api/v1/me
```

---

## Generate a local admin bearer token

Use the bootstrap script **inside the backend container** (recommended), so DB host `postgres` resolves correctly.

```bash
docker compose exec backend php bin/dev-bootstrap-admin-api-key.php \
  --dsn="pgsql:host=postgres;port=5432;dbname=lastmin" \
  --db-user="lastmin" \
  --db-pass="lastmin" \
  --actor-id="local-dev-admin" \
  --role="admin" \
  --name="Local admin token"
```

The script prints a one-time token line like:

```text
token: lm_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Export it for the session:

```bash
export ADMIN_TOKEN='paste_token_here'
```

> Why explicit `--dsn/--db-*`? The script defaults to `APP_DB_*` / sqlite fallback, while app runtime uses `DB_*`. Passing explicit DB args avoids ambiguity in local Docker.

---

## Authorization header usage

For protected endpoints, pass the token as a bearer token:

```bash
-H "Authorization: Bearer $ADMIN_TOKEN"
```

Example:

```bash
curl -sS \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  http://localhost:8080/api/v1/admin/organizations | jq
```

---

## Smoke test: admin + API key endpoints

Run in order.

Set helper variables first:

```bash
export API_BASE='http://localhost:8080/api/v1'
```

### 1) Create organization

```bash
ORG_ID=$(curl -sS -X POST "$API_BASE/admin/organizations" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "legal_name": "Smoke Test d.o.o.",
    "display_name": "Smoke Test Studio",
    "tax_id": "HR12345678901",
    "contact_email": "org-smoke@example.test",
    "contact_phone": "+385911234567"
  }' | jq -r '.data.organization_id')

echo "$ORG_ID"
```

### 2) Create provider

```bash
PROVIDER_ID=$(curl -sS -X POST "$API_BASE/admin/providers" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"organization_id\": \"$ORG_ID\",
    \"display_name\": \"Smoke Provider\",
    \"status\": \"active\"
  }" | jq -r '.data.provider_id')

echo "$PROVIDER_ID"
```

### 3) Create user

```bash
USER_ID=$(curl -sS -X POST "$API_BASE/admin/users" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"first_name\": \"Ana\",
    \"last_name\": \"Horvat\",
    \"email\": \"ana.$(date +%s)@example.test\",
    \"phone\": \"+38591111222\",
    \"roles\": [\"provider_staff\", \"provider_manager\"],
    \"provider_id\": \"$PROVIDER_ID\"
  }" | jq -r '.data.user_id')

echo "$USER_ID"
```

### 4) List endpoints

```bash
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/organizations" | jq
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/providers" | jq
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/users" | jq
```

Optional filtered list examples:

```bash
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/providers?organization_id=$ORG_ID" | jq
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/users?provider_id=$PROVIDER_ID" | jq
```

### 5) Detail endpoints

```bash
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/organizations/$ORG_ID" | jq
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/providers/$PROVIDER_ID" | jq
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/admin/users/$USER_ID" | jq
```

### 6) Create API key (client key)

`client_id` must be a UUID string. Example uses a static UUID:

```bash
CLIENT_ID='11111111-1111-4111-8111-111111111111'

API_KEY_ID=$(curl -sS -X POST "$API_BASE/api-key" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"client_id\": \"$CLIENT_ID\",
    \"name\": \"Smoke client key\"
  }" | tee /tmp/create_api_key_response.json | jq -r '.data.api_key_id')

echo "$API_KEY_ID"
echo "Plain key (shown once):"
jq -r '.data.api_key' /tmp/create_api_key_response.json
```

### 7) List API keys

```bash
curl -sS -H "Authorization: Bearer $ADMIN_TOKEN" "$API_BASE/api-keys?client_id=$CLIENT_ID" | jq
```

### 8) Revoke API key

```bash
curl -sS -X DELETE \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  "$API_BASE/api-key/$API_KEY_ID" | jq
```

---

## Troubleshooting

### `401 AUTH_IDENTITY_NOT_LINKED`
- Missing/invalid bearer token, or revoked token.
- Re-run admin token bootstrap and update `ADMIN_TOKEN`.

### `403 FORBIDDEN_ROLE_MISSING`
- Token is valid but actor role is not `admin` or `super-admin`.
- Bootstrap a token with `--role=admin` (or `super-admin`).

### `422` validation errors
- Payload shape does not match required fields.
- Confirm JSON keys exactly match examples (`legal_name`, `organization_id`, `provider_id`, etc.).

### Backend cannot connect to DB
- Ensure containers are up: `docker compose ps`.
- Re-run migrations: `docker compose run --rm backend php bin/migrate.php`.

### Dependency issues (`vendor/autoload.php` missing)
- Run `composer install` in repo root.

---

## Reset / cleanup

Stop containers:

```bash
docker compose down
```

Stop and remove volumes (wipes local PostgreSQL data):

```bash
docker compose down -v
```

Full reset (includes rebuilt images):

```bash
docker compose down -v --rmi local
```

Then start fresh:

```bash
composer install
cp .env.example .env
docker compose up --build -d
docker compose run --rm backend php bin/migrate.php
```

---

## Pointers to deeper docs

- Docker local development details: `docs/13-backend/local-development.md`
- Admin bearer bootstrap notes: `docs/11-technical-documentation/local-dev-admin-auth.md`
- Backend architecture index: `docs/13-backend/README.md`
- Technical docs index: `docs/11-technical-documentation/README.md`

