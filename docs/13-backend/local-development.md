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

## 4) Manual migration command (if needed)

```bash
docker compose run --rm backend php bin/migrate.php
```

## 5) Frontend integration target

Frontend should target this backend API base URL:

- `http://localhost:8080/api/v1`

## 6) Inspect running Postgres (optional)

```bash
docker compose exec postgres psql -U "$DB_USER" -d "$DB_NAME"
```

## Assumptions / limitations

- Local DB uses Postgres container service name `postgres` and DB env vars (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`).
- `DB_DSN` is optional; when empty, runtime composes a pgsql DSN from the `DB_*` values.
- Postgres data persists in `postgres_data` Docker volume.
- Stripe integration is stubbed in code; `STRIPE_WEBHOOK_SECRET` is used by webhook signature verification.
- Frontend service is intentionally left commented in `docker-compose.yml` as a placeholder TODO.
