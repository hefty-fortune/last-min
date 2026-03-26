# Locked Backend Foundation Summary (Canonical)

This file is the stable, high-level backend foundation for **U zadnji čas (Last minute)**.

## Locked technical direction

- **Architecture:** PHP 8+ modular monolith.
- **Style:** framework-agnostic core, adapter-friendly edges.
- **API:** REST under `/api/v1`.
- **Data:** relational database.
- **Payments:** Stripe is first-class (payments + webhooks).
- **Runtime:** async jobs are required.

## Identity and authorization boundary

- Shared SSO/auth backend owns registration and verification flows (OTP/phone verification are external).
- This backend consumes upstream authenticated identity and represents it locally as **ActorContext**.
- This backend owns authorization, role mapping, profile linkage, and business permissions.

## Actor model

- One global user can hold multiple roles.
- Roles: `client`, `provider`, `admin`, `super-admin`.
- Provider model supports both `individual` and `organization` from the foundation.

## Locked business rules

- Providers publish last-minute openings/slots.
- Clients reserve/book and pay.
- Clients cannot cancel.
- Client no-show is treated as consumed service.
- Provider no-show triggers refund behavior.
- Admin accounts are provisioned internally.
- Fast login and booking/claim flow are critical.

## Core safety and reliability requirements

- Idempotency keys for critical write flows.
- Transaction safety and locking for contention-sensitive paths (especially reservation).
- Webhook deduplication and retry-safe handling.
- Outbox pattern support at structural level.
- Audit logging for sensitive operations.
- Standardized API error envelope.
- Explicit module boundaries and maintainable dependency direction.

## Delivery posture

- Keep MVP scope focused and implementation-safe.
- Keep extension points explicit.
- Later artifact batches (domain/schema, API contracts, starter code) must remain consistent with this locked foundation.
