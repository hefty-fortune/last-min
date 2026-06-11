# Manual testing guide (per role)

The app runs locally via `docker compose up -d` (backend, frontend, postgres, worker).

| What | URL |
|---|---|
| App (storefront + portals) | http://localhost:5173 |
| API docs (Swagger) | http://localhost:8080/api/docs |

## Test accounts

One account per role — log out and back in to switch persona.

| Role | Email | Password | What they see after login |
|---|---|---|---|
| Client | `client@last-min.test` | `client1234` | Market, My bookings |
| Provider | `provider@last-min.test` | `provider1234` | Market, My bookings, Provider area |
| Admin | `admin@last-min.test` | `admin1234` | Market, My bookings, Admin dashboard |

Accounts are created with `php bin/create-user.php --email=... --password=... --roles=client[,provider,admin]` (run inside the backend container). If the database volume was wiped, recreate them.

## Script 1 — provider: publish a slot (~3 min)

Sign in as **provider@last-min.test**.

1. Open **Provider area**. First visit shows a *become a provider* prompt — click it. Expect: your provider profile appears.
2. Create an offering: name (e.g. "Haircut"), duration ≥ 5 min, price in EUR (e.g. `25` = €25.00). Expect: offering listed as `active`.
3. Create an opening from the offering (start/end times pre-filled). Expect: opening listed as `draft`.
4. Click **Publish**. Expect: status `published`.
5. Open **Market** — your slot is publicly visible as a card with your provider name, offering name, time, and price.

Also worth trying: delete a draft opening (confirmation dialog), edit/deactivate an offering, cancel a published opening.

## Script 2 — client: book and pay (~2 min)

Sign in as **client@last-min.test**.

1. **Market** shows the published slot from script 1. Click **Book now**. Expect: toast "Reserved!", auto-redirect to **My bookings**, booking shows service/provider/time with state `reserved` and a reservation expiry time.
2. Click **Pay**. Expect: payment badge `initiated`.
3. Click **Complete payment (simulated)**. Expect: booking flips to `confirmed`. (In real-Stripe mode this is replaced by the card dialog; in simulation only the booking's owner — or an admin — may settle it.)
4. Negative checks worth doing once:
   - Book a slot and let it sit 10+ minutes without paying → the worker expires it (`reservation_expired`) and the slot returns to Market.
   - Click **Fail payment (simulated)** instead → booking `payment_failed`, slot returns to Market.
   - Try opening http://localhost:5173/organizations as the client → bounced to Market (no admin access).

## Script 3 — provider: no-show on a confirmed booking (~1 min)

Sign in as **provider@last-min.test** (after script 2 confirmed a booking).

1. **Provider area → bookings**: the client's booking shows `confirmed`.
2. Click **Provider no-show** (confirmation expected — this triggers a refund). Expect: state `provider_no_show`.
3. Alternative path: **Client no-show** marks the service consumed; no refund is created.

## Script 4 — admin: refund approval and oversight (~2 min)

Sign in as **admin@last-min.test**.

1. **Admin dashboard → Refunds**: the refund from script 3 is `requested`. Click **Approve**. Expect: state `pending`.
2. Wait up to 30 seconds (worker loop), refresh: state `succeeded` with a gateway refund id. **Payments** list shows that payment `refunded`.
3. **Bookings / Payments / Webhook events** lists give full operational visibility with state filters.
4. Deletes: every entity list (organizations, providers, users, API keys, offerings, openings) has a **Delete** with a confirmation dialog. Deleting something still referenced (e.g. a provider with openings) returns a clear `409` error instead of breaking history.

## Script 5 — login rate limiting (~30 sec)

Sign out. Enter a wrong password for any account **5 times** — attempt 6 is blocked with "Too many failed login attempts" (HTTP 429) for 15 minutes. A successful login within the first 5 attempts resets the counter.

## Resetting test data

Wipe everything and start clean:

```bash
docker compose down -v && docker compose up -d
docker compose exec backend php bin/create-admin-user.php --email=admin@last-min.test --password=admin1234
docker compose exec backend php bin/create-user.php --email=client@last-min.test --password=client1234 --roles=client
docker compose exec backend php bin/create-user.php --email=provider@last-min.test --password=provider1234 --roles=provider
docker compose exec backend php bin/generate-frontend-api-key.php   # put the token in frontend/.env as VITE_API_KEY
```
