# Local development: admin bearer auth bootstrap

This backend keeps authorization checks on protected admin routes (`/api/v1/admin/*`) and now supports resolving bearer API keys into **admin/super-admin actor context**.

## What changed in auth resolution

- Bearer tokens still resolve through the API key repository.
- API keys are now actor-aware (`actor_type`, `actor_id`, `actor_roles`) and are no longer hardcoded to `client` role during token resolution.
- Existing client API key flow remains backward-compatible:
  - `POST /api/v1/api-key` still accepts `client_id` + `name`
  - client keys still resolve to actor role `client`

## Bootstrap an admin bearer token for local FE integration

Use the bootstrap CLI script (development only):

```bash
php bin/dev-bootstrap-admin-api-key.php \
  --dsn="sqlite:var/dev.sqlite" \
  --actor-id="local-dev-admin" \
  --role="admin" \
  --name="Local FE admin token"
```

The script prints a one-time plaintext token. Persist it in your FE `.env.local` (or Postman variable) immediately.
Only the token hash is stored in `api_keys.key_hash`.

### Optional PostgreSQL usage

If your local stack uses PostgreSQL:

```bash
php bin/dev-bootstrap-admin-api-key.php \
  --dsn="pgsql:host=localhost;port=5432;dbname=last_min" \
  --db-user="postgres" \
  --db-pass="postgres" \
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

- This is a thin development bridge; it does **not** introduce end-user login/password auth, sessions, cookies, or SSO.
- Do not use bootstrap-issued tokens in production.
