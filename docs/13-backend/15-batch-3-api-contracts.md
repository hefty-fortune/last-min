# Batch 3 — API Contracts (Canonical)

## 0) Scope, locked foundation, and assumptions

This batch defines only API-facing artifacts for MVP:

- REST API design principles
- route groups under `/api/v1`
- endpoint inventory by module
- OpenAPI-style contract drafts for critical endpoints
- request/response examples
- standardized API error format
- authentication/authorization behavior at API boundary
- Stripe webhook endpoint contract
- idempotency expectations for critical writes
- pagination/filter/sort conventions and admin visibility endpoints

Locked foundation this batch follows:

- framework-agnostic PHP 8+ modular monolith
- upstream SSO authentication, local business authorization
- provider openings, client booking/payment, no client cancellation
- provider no-show refund behavior
- idempotency, webhook deduplication, transactional safety

Assumptions made for contract clarity (safe defaults):

1. JSON over HTTPS only (`application/json`) for all API endpoints except Stripe signature validation requirement (raw body still accepted at transport level).
2. Monetary amounts use integer minor units (`amount_minor`, e.g., cents).
3. Time values use RFC3339 UTC (`2026-03-26T10:15:00Z`).
4. Pagination defaults to cursor-based for high-write resources; optional offset mode for admin list screens.

---

## 1) API design principles (MVP)

1. **Versioned REST namespace**: all business endpoints are under `/api/v1`.
2. **Role-aware boundary**: endpoint access is explicit by role (`client`, `provider`, `admin`, `super_admin`).
3. **Upstream identity consumption**: API trusts authenticated identity forwarded by trusted edge; API still validates actor linkage and role grants locally.
4. **Idempotent critical writes**: writes that create booking/payment/refund effects require `Idempotency-Key`.
5. **Predictable state transitions**: endpoint naming favors explicit actions where business meaning is important (e.g., `:mark-no-show`, `:capture`).
6. **Standard error envelope**: every non-2xx response follows one shared error object.
7. **Operational visibility**: admin endpoints expose traceable statuses, webhook ingestion state, and retry-safe read models.
8. **Concurrency safety by contract**: reservation and booking endpoints expose conflict semantics (`409`, `423`) instead of implicit race behavior.
9. **Separation of command vs query intent**: state-changing endpoints are not overloaded with list/read concerns.
10. **Extension-safe DTOs**: responses include stable identifiers and status fields, with optional expandable fields later.

---

## 2) Route map under `/api/v1` (first output artifact)

> Prefix legend:
> - `AUTH` = requires upstream-authenticated identity
> - `ROLE(...)` = locally authorized roles
> - `IDEMP` = idempotency key required

### 2.1 Identity and profile

- `GET /api/v1/me` — AUTH, ROLE(any authenticated)
- `PATCH /api/v1/me/profile` — AUTH, ROLE(client|provider|admin|super_admin)
- `GET /api/v1/me/roles` — AUTH, ROLE(any authenticated)
- `POST /api/v1/me/provider-link` — AUTH, ROLE(provider), IDEMP

Note on creation semantics:

- `POST /api/v1/providers` is the canonical provider creation endpoint.
- `POST /api/v1/me/provider-link` is a self-service convenience endpoint for authenticated providers that internally delegates to canonical provider creation/linking rules for `provider_type=individual`.
- Organization-backed provider creation should use canonical provider/org endpoints to keep ownership and membership policies explicit.

### 2.2 Providers and organizations

- `POST /api/v1/providers` — AUTH, ROLE(provider), IDEMP
- `GET /api/v1/providers/{provider_id}` — AUTH, ROLE(provider|admin|super_admin)
- `PATCH /api/v1/providers/{provider_id}` — AUTH, ROLE(provider owner|org manager|admin|super_admin)
- `POST /api/v1/organizations` — AUTH, ROLE(provider), IDEMP
- `GET /api/v1/organizations/{organization_id}` — AUTH, ROLE(org member|admin|super_admin)
- `POST /api/v1/organizations/{organization_id}/members` — AUTH, ROLE(org owner|org manager|admin|super_admin), IDEMP
- `PATCH /api/v1/organizations/{organization_id}/members/{member_id}` — AUTH, ROLE(org owner|admin|super_admin)

### 2.3 Service catalog

- `POST /api/v1/providers/{provider_id}/offerings` — AUTH, ROLE(provider owner|org manager), IDEMP
- `GET /api/v1/providers/{provider_id}/offerings` — AUTH, ROLE(provider staff|admin|super_admin)
- `PATCH /api/v1/providers/{provider_id}/offerings/{offering_id}` — AUTH, ROLE(provider owner|org manager)
- `GET /api/v1/public/providers/{provider_id}/offerings` — public read (optionally authenticated)

### 2.4 Openings (availability)

- `POST /api/v1/providers/{provider_id}/openings` — AUTH, ROLE(provider owner|org manager), IDEMP
- `GET /api/v1/providers/{provider_id}/openings` — AUTH, ROLE(provider staff|admin|super_admin)
- `PATCH /api/v1/providers/{provider_id}/openings/{opening_id}` — AUTH, ROLE(provider owner|org manager)
- `POST /api/v1/providers/{provider_id}/openings/{opening_id}:publish` — AUTH, ROLE(provider owner|org manager), IDEMP
- `GET /api/v1/public/openings` — public/client discovery

### 2.5 Booking

- `POST /api/v1/bookings` — AUTH, ROLE(client), IDEMP
- `GET /api/v1/bookings/{booking_id}` — AUTH, ROLE(client owner|provider owner|org manager|admin|super_admin)
- `GET /api/v1/me/bookings` — AUTH, ROLE(client)
- `POST /api/v1/bookings/{booking_id}:mark-provider-no-show` — AUTH, ROLE(provider owner|org manager|admin|super_admin), IDEMP
- `POST /api/v1/bookings/{booking_id}:mark-client-no-show` — AUTH, ROLE(provider owner|org manager|admin|super_admin), IDEMP

### 2.6 Payments and refunds

- `POST /api/v1/bookings/{booking_id}/payments/initiate` — AUTH, ROLE(client), IDEMP
- `GET /api/v1/bookings/{booking_id}/payments/{payment_id}` — AUTH, ROLE(client owner|provider owner|org manager|admin|super_admin)
- `GET /api/v1/payments/{payment_id}` — AUTH, ROLE(client owner|provider owner|org manager|admin|super_admin)
- `GET /api/v1/bookings/{booking_id}/refunds` — AUTH, ROLE(client owner|provider owner|org manager|admin|super_admin)
- `POST /api/v1/refunds/{refund_id}:approve` — AUTH, ROLE(admin|super_admin), IDEMP

### 2.7 Admin operations

- `GET /api/v1/admin/bookings` — AUTH, ROLE(admin|super_admin)
- `GET /api/v1/admin/payments` — AUTH, ROLE(admin|super_admin)
- `GET /api/v1/admin/refunds` — AUTH, ROLE(admin|super_admin)
- `GET /api/v1/admin/webhooks/stripe/events` — AUTH, ROLE(admin|super_admin)
- `POST /api/v1/admin/openings/{opening_id}:force-expire` — AUTH, ROLE(super_admin), IDEMP

### 2.8 Webhooks and callbacks

- `POST /api/v1/webhooks/stripe` — unauthenticated user context; authenticated by Stripe signature header, deduplicated by event id
- `POST /api/v1/internal/callbacks/payment-reconciliation` — service-to-service callback using internal HMAC key or mTLS, IDEMP

---

## 3) Endpoint inventory by module

| Module | Endpoints (MVP) |
|---|---|
| IdentityAccess + UserProfiles | `/me`, `/me/profile`, `/me/roles`, `/me/provider-link` |
| Providers | `/providers`, `/providers/{id}`, provider status/profile updates |
| Organizations | `/organizations`, `/organizations/{id}`, `/members` management |
| ServiceCatalog | `/providers/{id}/offerings` CRUD-lite |
| Openings | `/providers/{id}/openings`, publish/update/list, public search |
| Booking | `/bookings` create/read, no-show actions, `/me/bookings` |
| Payments | payment initiation and payment status reads |
| Refunds | booking refund read models, admin approval action |
| Admin | operational list endpoints + forced operational actions |
| WebhookProcessing (platform capability) | `/webhooks/stripe`, admin webhook event read model |

---

## 4) Shared schemas / DTO-like contracts

## 4.1 Common primitives

```yaml
UUID:
  type: string
  format: uuid

Timestamp:
  type: string
  format: date-time

Money:
  type: object
  required: [currency, amount_minor]
  properties:
    currency: { type: string, example: "EUR" }
    amount_minor: { type: integer, example: 4500 }
```

## 4.2 Actor and role models

```yaml
ActorIdentity:
  type: object
  required: [actor_id, upstream_subject, roles]
  properties:
    actor_id: { $ref: '#/components/schemas/UUID' }
    upstream_subject: { type: string, example: "sso|9a4f..." }
    roles:
      type: array
      items: { type: string, enum: [client, provider, admin, super_admin] }

RoleGrant:
  type: object
  required: [role, source, granted_at]
  properties:
    role: { type: string }
    source: { type: string, enum: [system, admin_assignment, organization_membership] }
    granted_at: { $ref: '#/components/schemas/Timestamp' }
```

## 4.3 Core business DTOs

```yaml
Provider:
  type: object
  required: [provider_id, provider_type, status, display_name]
  properties:
    provider_id: { $ref: '#/components/schemas/UUID' }
    provider_type: { type: string, enum: [individual, organization] }
    status: { type: string, enum: [onboarding, active, suspended] }
    display_name: { type: string }
    organization_id:
      oneOf:
        - { $ref: '#/components/schemas/UUID' }
        - { type: 'null' }

ServiceOffering:
  type: object
  required: [offering_id, provider_id, name, duration_minutes, base_price, status]
  properties:
    offering_id: { $ref: '#/components/schemas/UUID' }
    provider_id: { $ref: '#/components/schemas/UUID' }
    name: { type: string }
    description: { type: string }
    duration_minutes: { type: integer, minimum: 5 }
    base_price: { $ref: '#/components/schemas/Money' }
    status: { type: string, enum: [active, inactive] }

Opening:
  type: object
  required: [opening_id, provider_id, service_offering_id, starts_at, ends_at, status]
  properties:
    opening_id: { $ref: '#/components/schemas/UUID' }
    provider_id: { $ref: '#/components/schemas/UUID' }
    service_offering_id: { $ref: '#/components/schemas/UUID' }
    starts_at: { $ref: '#/components/schemas/Timestamp' }
    ends_at: { $ref: '#/components/schemas/Timestamp' }
    status: { type: string, enum: [draft, published, reserved, booked, expired, cancelled_by_provider] }
    price_snapshot: { $ref: '#/components/schemas/Money' }

Booking:
  type: object
  required: [booking_id, opening_id, client_user_profile_id, state, reserved_at, expires_at]
  properties:
    booking_id: { $ref: '#/components/schemas/UUID' }
    opening_id: { $ref: '#/components/schemas/UUID' }
    client_user_profile_id: { $ref: '#/components/schemas/UUID' }
    state:
      type: string
      enum: [initiated, reserved, payment_pending, confirmed, completed, client_no_show, provider_no_show, cancelled_by_provider, reservation_expired, payment_failed, refunded]
    reserved_at: { $ref: '#/components/schemas/Timestamp' }
    expires_at: { $ref: '#/components/schemas/Timestamp' }

Payment:
  type: object
  required: [payment_id, booking_id, state, amount, provider_amount, platform_fee]
  properties:
    payment_id: { $ref: '#/components/schemas/UUID' }
    booking_id: { $ref: '#/components/schemas/UUID' }
    state: { type: string, enum: [initiated, authorized, captured, failed, refunded] }
    amount: { $ref: '#/components/schemas/Money' }
    provider_amount: { $ref: '#/components/schemas/Money' }
    platform_fee: { $ref: '#/components/schemas/Money' }
    stripe_payment_intent_id: { type: string }
    gateway_status:
      type: object
      description: External gateway status projection (Stripe), intentionally separate from internal `state`.
      properties:
        provider: { type: string, enum: [stripe] }
        status: { type: string, example: "requires_action" }
        last_event_at: { $ref: '#/components/schemas/Timestamp' }

Refund:
  type: object
  required: [refund_id, payment_id, state, reason, amount]
  properties:
    refund_id: { $ref: '#/components/schemas/UUID' }
    payment_id: { $ref: '#/components/schemas/UUID' }
    state: { type: string, enum: [requested, pending, succeeded, failed, cancelled] }
    reason: { type: string, enum: [provider_no_show, admin_override, technical_failure] }
    amount: { $ref: '#/components/schemas/Money' }
```

## 4.4 Envelope and pagination

```yaml
ApiResponseMeta:
  type: object
  properties:
    request_id: { type: string }
    idempotency_replayed: { type: boolean, default: false }

CursorPage:
  type: object
  required: [items, page]
  properties:
    items:
      type: array
      items: { type: object }
    page:
      type: object
      properties:
        next_cursor: { type: string, nullable: true }
        prev_cursor: { type: string, nullable: true }
        limit: { type: integer }
```

---

## 5) Standardized API error format

All errors (4xx/5xx) must use:

```yaml
ApiError:
  type: object
  required: [error]
  properties:
    error:
      type: object
      required: [code, message]
      properties:
        code:
          type: string
          example: "BOOKING_OPENING_ALREADY_RESERVED"
        message:
          type: string
          example: "The selected opening is no longer available."
        details:
          type: array
          items:
            type: object
            properties:
              field: { type: string, example: "opening_id" }
              issue: { type: string, example: "not_available" }
        retryable: { type: boolean, example: false }
        request_id: { type: string }
        idempotency_key: { type: string }
```

Error code families:

- `AUTH_*` (missing/invalid upstream identity mapping)
- `FORBIDDEN_*` (role/policy denied)
- `VALIDATION_*` (input contract failures)
- `BOOKING_*` (reservation lifecycle errors)
- `PAYMENT_*` (payment flow errors)
- `REFUND_*` (refund flow errors)
- `WEBHOOK_*` (signature/dedup/processing issues)
- `CONFLICT_*` (idempotency or version conflicts)
- `INTERNAL_*` (unexpected failures)

---

## 6) OpenAPI-style endpoint contracts for critical MVP flows

## 6.1 Current user identity linkage

### `GET /api/v1/me`

- **Purpose:** return local actor context linked to upstream SSO subject.
- **Auth requirement:** upstream authenticated identity required; any mapped role.
- **Request body:** none.
- **Response body (200):**

```json
{
  "data": {
    "actor_id": "29eb9f44-74f2-42f2-80d5-53a1f3da1de1",
    "upstream_subject": "sso|user_001",
    "roles": ["client", "provider"],
    "default_role": "client",
    "profile_id": "d9a4d241-28cb-4f31-b6f8-0f050f55383d"
  },
  "meta": { "request_id": "req_01J..." }
}
```

- **Common errors:** `AUTH_IDENTITY_NOT_LINKED (401)`, `FORBIDDEN_ROLE_MISSING (403)`.
- **Idempotency behavior:** not applicable (read).

### `POST /api/v1/me/provider-link`

- **Purpose:** link authenticated actor to provider profile (individual or organization context).
- **Auth requirement:** ROLE(provider).
- **Request headers:** `Idempotency-Key: <uuid>`.
- **Request body:**

```json
{
  "provider_type": "individual",
  "display_name": "Ana Horvat"
}
```

- **Response body (201):**

```json
{
  "data": {
    "provider_id": "3f0a83e2-8f53-4ad4-9fe9-8f4dc4e1f111",
    "provider_type": "individual",
    "status": "active"
  },
  "meta": {
    "request_id": "req_01J...",
    "idempotency_replayed": false
  }
}
```

- **Common errors:** `VALIDATION_PROVIDER_TYPE_INVALID (422)`, `CONFLICT_PROVIDER_ALREADY_LINKED (409)`.
- **Idempotency behavior:** same key + same actor + same payload returns original 201 body; payload mismatch with reused key returns `409 CONFLICT_IDEMPOTENCY_PAYLOAD_MISMATCH`.
- **Clarification:** this endpoint is self-service convenience. Canonical provider creation remains `POST /api/v1/providers`; this endpoint applies the same domain constraints and is limited to caller-owned individual provider linkage.

## 6.2 Provider profile and organization management

### `POST /api/v1/organizations`

- **Purpose:** create organization for provider operations.
- **Auth requirement:** ROLE(provider).
- **Headers:** `Idempotency-Key` required.
- **Request body:**

```json
{
  "legal_name": "Studio Brzo d.o.o.",
  "country_code": "HR",
  "default_timezone": "Europe/Zagreb"
}
```

- **Response body (201):**

```json
{
  "data": {
    "organization_id": "6ce29cf8-2010-4fce-9941-9b8fccf6dbf0",
    "status": "active"
  }
}
```

- **Common errors:** `VALIDATION_REQUIRED_FIELD (422)`, `FORBIDDEN_ROLE_MISSING (403)`.
- **Idempotency:** required.

### `POST /api/v1/organizations/{organization_id}/members`

- **Purpose:** add member with role (`owner|manager|staff`).
- **Auth requirement:** ROLE(org owner|org manager|admin|super_admin).
- **Headers:** `Idempotency-Key` required.
- **Request body:**

```json
{
  "actor_id": "79cf1f4a-9f4d-4489-a8a6-f32b8fd97d6f",
  "organization_role": "manager"
}
```

- **Response (201):** member link DTO.
- **Common errors:** `FORBIDDEN_POLICY_DENIED (403)`, `CONFLICT_MEMBER_ALREADY_EXISTS (409)`.
- **Idempotency:** required.

## 6.3 Service/offering management

### `POST /api/v1/providers/{provider_id}/offerings`

- **Purpose:** create offering that can be attached to openings.
- **Auth requirement:** ROLE(provider owner|org manager).
- **Headers:** `Idempotency-Key` required.
- **Request body:**

```json
{
  "name": "Haircut - 30 min",
  "description": "Standard cut",
  "duration_minutes": 30,
  "base_price": { "currency": "EUR", "amount_minor": 2500 }
}
```

- **Response (201):** `ServiceOffering`.
- **Common errors:** `VALIDATION_DURATION_INVALID (422)`, `FORBIDDEN_PROVIDER_SCOPE (403)`.
- **Idempotency:** required.

### `GET /api/v1/providers/{provider_id}/offerings`

- **Purpose:** provider/admin listing with filters.
- **Auth requirement:** ROLE(provider staff|admin|super_admin).
- **Query params:** `status`, `limit`, `cursor`, `sort=created_at|-created_at`.
- **Response (200):** cursor page of `ServiceOffering`.
- **Common errors:** `VALIDATION_INVALID_CURSOR (422)`.

## 6.4 Opening/availability management

### `POST /api/v1/providers/{provider_id}/openings`

- **Purpose:** create draft opening.
- **Auth requirement:** ROLE(provider owner|org manager).
- **Headers:** `Idempotency-Key` required.
- **Request body:**

```json
{
  "service_offering_id": "5fa66cd9-8d76-4c54-bcc9-e65c9df0fa92",
  "starts_at": "2026-03-29T12:00:00Z",
  "ends_at": "2026-03-29T12:30:00Z",
  "price_override": { "currency": "EUR", "amount_minor": 2200 }
}
```

- **Response (201):** `Opening` with `status=draft`.
- **Common errors:** `VALIDATION_TIME_RANGE_INVALID (422)`, `CONFLICT_OPENING_OVERLAP (409)`.
- **Idempotency:** required.

### `POST /api/v1/providers/{provider_id}/openings/{opening_id}:publish`

- **Purpose:** publish opening for client discovery.
- **Auth requirement:** ROLE(provider owner|org manager).
- **Headers:** `Idempotency-Key` required.
- **Request body:** empty object `{}`.
- **Response (200):** `Opening` with `status=published`.
- **Common errors:** `CONFLICT_OPENING_STATE_INVALID (409)`.
- **Idempotency:** required; repeated publish on same key returns same response.

### `GET /api/v1/public/openings`

- **Purpose:** client/public discovery of published openings.
- **Auth requirement:** none (optional auth for personalization).
- **Query params:** `city`, `starts_before`, `starts_after`, `max_price_minor`, `provider_id`, `service_offering_id`, `limit`, `cursor`, `sort=starts_at|-starts_at`.
- **Response (200):** cursor page of public `Opening` projections.
- **Common errors:** `VALIDATION_FILTER_INVALID (422)`.

## 6.5 Booking creation and retrieval

### `POST /api/v1/bookings`

- **Purpose:** reserve published opening for authenticated client.
- **Auth requirement:** ROLE(client).
- **Headers:** `Idempotency-Key` required.
- **Request body:**

```json
{
  "opening_id": "f115f130-0e1e-4418-a237-5f2dc77a5829",
  "client_note": "I'll arrive 5 min early"
}
```

- **Response (201):**

```json
{
  "data": {
    "booking_id": "09874022-44e7-4a11-b152-64157ff4ee69",
    "opening_id": "f115f130-0e1e-4418-a237-5f2dc77a5829",
    "state": "reserved",
    "reserved_at": "2026-03-26T10:20:00Z",
    "expires_at": "2026-03-26T10:30:00Z"
  },
  "meta": {
    "request_id": "req_01J...",
    "idempotency_replayed": false
  }
}
```

- **Common errors:** `BOOKING_OPENING_ALREADY_RESERVED (409)`, `BOOKING_OPENING_NOT_PUBLISHED (409)`, `FORBIDDEN_ROLE_MISSING (403)`.
- **Idempotency behavior:** required; reservation logic must be transaction + lock protected; same key safe-replays response.

### `GET /api/v1/bookings/{booking_id}`

- **Purpose:** booking details for permitted actors.
- **Auth requirement:** owner/policy checks.
- **Response (200):** `Booking` + related payment/refund summary.
- **Common errors:** `FORBIDDEN_BOOKING_SCOPE (403)`, `BOOKING_NOT_FOUND (404)`.

## 6.6 Payment initiation and status

### `POST /api/v1/bookings/{booking_id}/payments/initiate`

- **Purpose:** create or reuse payment intent for booking.
- **Auth requirement:** ROLE(client owner of booking).
- **Headers:** `Idempotency-Key` required.
- **Request body:**

```json
{
  "payment_method_type": "card",
  "return_url": "https://app.example.com/payment-return"
}
```

- **Response (201):**

```json
{
  "data": {
    "payment_id": "7f6329ee-3094-4fa0-a3b0-c8fbf8cb8627",
    "state": "initiated",
    "amount": { "currency": "EUR", "amount_minor": 2200 },
    "gateway_status": {
      "provider": "stripe",
      "status": "requires_action"
    },
    "stripe": {
      "payment_intent_id": "pi_3Q...",
      "client_secret": "pi_3Q..._secret_..."
    }
  }
}
```

- **Common errors:** `PAYMENT_BOOKING_STATE_INVALID (409)`, `PAYMENT_PROVIDER_ACCOUNT_UNAVAILABLE (422)`.
- **Idempotency:** required; same booking + same key returns same initiated payment reference.

### `GET /api/v1/payments/{payment_id}`

- **Purpose:** retrieve payment status.
- **Auth requirement:** owner/provider/admin policy.
- **Response (200):** `Payment`.
- **Common errors:** `PAYMENT_NOT_FOUND (404)`, `FORBIDDEN_PAYMENT_SCOPE (403)`.

## 6.7 Refund-related read models and actions

### `GET /api/v1/bookings/{booking_id}/refunds`

- **Purpose:** return refund history and current refund state for booking.
- **Auth requirement:** booking scope policy.
- **Response (200):** list of `Refund` records.
- **Common errors:** `BOOKING_NOT_FOUND (404)`, `FORBIDDEN_REFUND_SCOPE (403)`.
- **Idempotency:** not applicable (read).

### `POST /api/v1/refunds/{refund_id}:approve`

- **Purpose:** admin approval/cancellation decision for **manual exception workflows only**.
- **Auth requirement:** ROLE(admin|super_admin).
- **Headers:** `Idempotency-Key` required.
- **Request body:**

```json
{ "note": "Provider no-show confirmed by support evidence." }
```

- **Response (200):** updated `Refund` with state transition constrained to batch-2 flow (for example `requested -> pending` or `requested -> cancelled`).
- **Common errors:** `REFUND_STATE_INVALID (409)`, `FORBIDDEN_ROLE_MISSING (403)`.
- **Idempotency:** required.
- **Clarification:** provider no-show refund handling is system-driven (`provider_no_show` booking outcome triggers refund workflow); this endpoint is reserved for human-reviewed exceptions and overrides.

## 6.8 Admin operational endpoints

### `GET /api/v1/admin/webhooks/stripe/events`

- **Purpose:** operational visibility for ingested webhook events and processing status.
- **Auth requirement:** ROLE(admin|super_admin).
- **Query params:** `event_type`, `status`, `received_after`, `received_before`, `limit`, `cursor`.
- **Response (200):** cursor page of webhook event read model (`event_id`, `dedup_key`, `processing_status`, `last_error`).
- **Common errors:** `FORBIDDEN_ROLE_MISSING (403)`.

### `GET /api/v1/admin/bookings`

- **Purpose:** operational list for dispute/no-show oversight.
- **Auth requirement:** ROLE(admin|super_admin).
- **Query params:** `state`, `provider_id`, `client_user_profile_id`, `created_after`, `created_before`, `limit`, `cursor`, `sort=-created_at`.
- **Response (200):** list projection with booking + payment/refund summary fields.

## 6.9 Stripe webhook ingestion endpoint

### `POST /api/v1/webhooks/stripe`

- **Purpose:** ingest Stripe events (`payment_intent.*`, `charge.refunded`, etc.) and dispatch internal handlers.
- **Auth requirement:** no ActorContext; verified with Stripe signature.
- **Headers required:**
  - `Stripe-Signature`
  - `Content-Type: application/json`
- **Request body:** raw Stripe event payload.
- **Response body:**
  - `200` when accepted/processed (or safely deduplicated)
  - `400` for signature validation failure
  - `500` only for transient internal failures (Stripe retries)

Minimal response example:

```json
{ "received": true }
```

- **Common errors:** `WEBHOOK_SIGNATURE_INVALID (400)`, `WEBHOOK_EVENT_UNSUPPORTED (200 with ignored=true metadata or 202 depending policy)`, `INTERNAL_WEBHOOK_PROCESSING_FAILED (500)`.
- **Idempotency behavior:** deduplicate by `stripe_event_id`; re-delivered events must not double-apply side effects.

## 6.10 Internal callback pattern (optional but prepared)

### `POST /api/v1/internal/callbacks/payment-reconciliation`

- **Purpose:** internal async reconciliation callback from trusted runtime worker.
- **Auth requirement:** internal HMAC or mTLS; not user token.
- **Headers:** `X-Internal-Signature`, `Idempotency-Key`.
- **Request body:** reconciliation batch result (`payment_id`, external status, observed_at).
- **Response:** `202 Accepted`.
- **Idempotency:** required to avoid duplicate correction jobs.

---

## 7) Authentication and authorization behavior at API boundary

## 7.1 Upstream SSO-authenticated identity arrives externally

1. Edge/API gateway authenticates user via shared SSO.
2. Gateway forwards trusted identity claims (e.g., subject, tenant/context, assurance level).
3. Backend constructs `ActorContext` from forwarded claims.
4. Backend resolves local actor linkage and role mappings.

If linkage missing, return `401 AUTH_IDENTITY_NOT_LINKED` (or bootstrap flow endpoint if allowed).

## 7.2 This backend enforces business authorization

- Authorization is local, policy-based, and resource-scoped.
- Role alone is insufficient: endpoint policy must verify ownership/membership/resource scope.
- Examples:
  - client can only read own bookings/payments/refunds
  - provider manager can manage openings for own provider/org only
  - admin/super_admin have operational visibility; super_admin-only destructive overrides

## 7.3 Role-aware endpoint policy matrix (MVP shorthand)

| Endpoint group | client | provider (owner/manager/staff) | admin | super_admin |
|---|---:|---:|---:|---:|
| `/me*` | ✅ | ✅ | ✅ | ✅ |
| provider setup/manage | ❌ | ✅ | ✅ | ✅ |
| offering/opening manage | ❌ | ✅ | ✅ (read/override by policy) | ✅ |
| public openings read | ✅ | ✅ | ✅ | ✅ |
| booking create | ✅ | ❌ | ❌ | ❌ |
| booking read (scoped) | owner only | scoped | ✅ | ✅ |
| payment initiate | booking owner | ❌ | ❌ | ❌ |
| refund approve | ❌ | ❌ | ✅ | ✅ |
| admin operations | ❌ | ❌ | ✅ | ✅ |
| webhook ingestion | n/a | n/a | n/a | n/a |

---

## 8) Idempotency expectations for critical endpoints

## 8.1 Required idempotency-key endpoints

- provider/profile/org creation and membership commands
- offering and opening creation/publish commands
- booking creation
- payment initiation
- no-show marking commands
- manual/admin refund approval
- super-admin force operational commands
- internal reconciliation callbacks

## 8.2 Contract rules

1. Client sends `Idempotency-Key` header (UUID v4 recommended).
2. Server scopes key by `(actor_id OR system_client_id, route template, method)`.
3. Server stores request hash + final response envelope.
4. Retry with same key + same hash returns stored response (`idempotency_replayed=true`).
5. Same key + different hash returns `409 CONFLICT_IDEMPOTENCY_PAYLOAD_MISMATCH`.
6. Expiry TTL recommended: 24h minimum for user writes; 72h for webhook/internal callbacks.

---

## 9) Pagination, filtering, sorting, and admin endpoint behavior

## 9.1 Pagination

- Default: cursor pagination (`limit`, `cursor`).
- Maximum `limit`: 100; default 20.
- Cursor is opaque and stable for selected sort order.

## 9.2 Filtering

- Use flat query params for common filters (`status`, `provider_id`, date ranges).
- Range suffix convention:
  - `_after` (inclusive lower bound)
  - `_before` (exclusive upper bound)

## 9.3 Sorting

- `sort` parameter supports one field in MVP.
- Descending uses `-` prefix (`sort=-created_at`).
- Unsupported sort field => `422 VALIDATION_SORT_FIELD_UNSUPPORTED`.

## 9.4 Admin endpoints

- Must expose operational status projections (booking/payment/refund/webhook states).
- Must include `request_id` in response meta for incident traceability.
- Sensitive action endpoints require audit emission and idempotency.

---

## 10) Minimal headers and status code conventions

Required response headers:

- `X-Request-Id`
- `Content-Type: application/json`

Conditionally required request headers:

- `Authorization` or trusted upstream identity headers (depending deployment boundary)
- `Idempotency-Key` for critical writes
- `Stripe-Signature` for Stripe webhook ingress

Status code patterns:

- `200` read/action success
- `201` resource created
- `202` async accepted (internal callbacks where appropriate)
- `204` mutation success without body (optional for some patch/delete in future)
- `400` malformed/signature failure
- `401` unauthenticated/unlinked identity
- `403` authenticated but unauthorized
- `404` not found
- `409` state/idempotency conflict
- `422` validation failure
- `429` rate-limited
- `500` unexpected server error

---

## 11) MVP vs extension points

## MVP locked for implementation

- Endpoint groups and critical flows listed in Sections 2 and 6
- Standard error envelope in Section 5
- Idempotency requirements in Section 8
- Stripe webhook endpoint behavior in Section 6.9

## Explicit extension points (not required for MVP completion)

- richer search facets and geo filtering for public openings
- partial capture / advanced payment methods
- dispute/chargeback API surfaces
- webhook dead-letter replay endpoint for admins
- bulk admin mutation endpoints
