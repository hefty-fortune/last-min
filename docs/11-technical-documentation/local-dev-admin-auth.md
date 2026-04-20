# Local development: admin bearer auth bootstrap

This backend keeps authorization checks on protected admin routes (`/api/v1/admin/*`) and supports resolving bearer API keys into **admin/super-admin actor context**.

## What changed in auth resolution

- Bearer tokens resolve through the API key repository.
- API keys are actor-aware (`actor_type`, `actor_id`, `actor_roles`) and are not hardcoded to `client` role.
- Existing client API key flow remains backward-compatible:
  - `POST /api/v1/api-key` accepts `client_id` + `name`
  - client keys still resolve to actor role `client`

## Bootstrap an admin bearer token for local FE integration

Recommended: run from inside the backend container so Postgres host `postgres` resolves correctly.

```bash
docker compose exec backend php bin/dev-bootstrap-admin-api-key.php \
  --dsn="pgsql:host=postgres;port=5432;dbname=lastmin" \
  --db-user="lastmin" \
  --db-pass="lastmin" \
  --actor-id="local-dev-admin" \
  --role="admin" \
  --name="Local FE admin token"
```

The script prints a one-time plaintext token. Save it immediately in FE `.env.local` (or a Postman variable).
Only the token hash is stored in `api_keys.key_hash`.

### Host machine alternative

If you run the script on your host instead of inside the container, use `localhost` for DB host:

```bash
php bin/dev-bootstrap-admin-api-key.php \
  --dsn="pgsql:host=localhost;port=5432;dbname=lastmin" \
  --db-user="lastmin" \
  --db-pass="lastmin" \
  --actor-id="local-dev-admin" \
  --role="admin"
```

## Use the token against protected admin routes

```bash
curl -H "Authorization: Bearer <token>" \
  http://localhost:8080/api/v1/admin/organizations
```

Expected result: `200` when the token maps to `admin` or `super-admin`, `403` otherwise.

## Notes

- This is a local development bridge; it does **not** introduce login/password auth, sessions, cookies, or SSO.
- Do not use bootstrap-issued tokens in production.
- Canonical setup and smoke tests now live in the repository root `README.md`.
