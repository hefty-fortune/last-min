# Frontend

React + TypeScript + Vite dashboard for the Last-Min platform.

## Setup

### 1. Generate the Frontend API Key

The frontend needs an API key to communicate with the backend. Generate one from the project root:

```bash
docker compose exec backend php bin/generate-frontend-api-key.php
```

This outputs something like:

```
VITE_API_KEY=lm_a1b2c3d4e5f6...
```

Copy that value into `frontend/.env`:

```bash
# frontend/.env
VITE_API_KEY=lm_a1b2c3d4e5f6...
```

If running via Docker Compose, you can also set `VITE_API_KEY` in the root `.env` file (it's passed through to the frontend container).

### 2. Create an Initial User Account

First, create an organization and provider via the admin API (using the bootstrap admin key), then create a user with a password:

**Option A: Set password via CLI**

If a user already exists in the system:

```bash
docker compose exec backend php bin/set-user-password.php --email=user@example.com --password=yourpassword
```

**Option B: Create a user with password via the Admin API**

```bash
curl -X POST http://localhost:8080/api/v1/admin/users \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: <your-api-key>" \
  -H "Authorization: Bearer <admin-api-key>" \
  -d '{
    "first_name": "Admin",
    "last_name": "User",
    "email": "admin@example.com",
    "phone": "+385911234567",
    "roles": ["admin"],
    "provider_id": "<provider-id>",
    "password": "yourpassword"
  }'
```

### 3. Login

Open `http://localhost:5173` and sign in with the email and password you created.

## Development

```bash
# With Docker (recommended)
docker compose up

# Standalone
cd frontend
npm install
npm run dev
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `VITE_API_KEY` | API key for frontend-to-backend authorization (required) |
