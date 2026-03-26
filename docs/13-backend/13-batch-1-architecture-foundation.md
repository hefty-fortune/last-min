# Batch 1 — Architecture Foundation (Canonical)

## 1) Architecture overview

`Last minute` backend is a **PHP 8+ framework-agnostic modular monolith** optimized for safe, fast booking and payment flows.

Locked characteristics:

- REST API under `/api/v1`.
- Relational database.
- Stripe payments and Stripe webhooks as first-class integrations.
- Async jobs for retry-safe background processing.
- Upstream SSO for authentication; local backend authorization and business permissions.
- Idempotency + transaction + locking for critical write paths.
- Outbox and audit structures as foundational capabilities.
- Standardized API errors across modules.

---

## 2) Canonical context for later batches

The following files are canonical context for later deliverables:

- `docs/13-backend/00-locked-foundation-summary.md`
- `docs/13-backend/13-batch-1-architecture-foundation.md`

Batches 2, 3, and 4 must stay consistent with these files.

---

## 3) Corrected business module list (locked)

- `IdentityAccess`
- `UserProfiles`
- `Providers`
- `Organizations`
- `ServiceCatalog`
- `Openings`
- `Booking`
- `Payments`
- `Refunds`
- `Payouts`
- `Notifications`
- `Admin`
- `AuditLogging`

### Notes

- `ActorContext` is the canonical local representation of authenticated upstream identity.
- No-show policy is part of **Booking** domain behavior for MVP.
- Booking emits events/outbox messages that integrate with Refunds and Notifications.
- `WebhookProcessing`, `AsyncJobProcessing`, idempotency runtime, outbox relay, queue runtime, and observability are **platform/runtime capabilities**, not peer business modules.

---

## 4) Repository structure (corrected)

```text
last-min/
├── docs/
│   └── 13-backend/
│       ├── 00-locked-foundation-summary.md
│       ├── 13-batch-1-architecture-foundation.md
│       ├── 14-batch-2-domain-and-schema.md
│       ├── 15-batch-3-api-contracts.md
│       └── 16-batch-4-starter-code-skeleton.md
├── src/
│   ├── Modules/
│   │   ├── IdentityAccess/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── UserProfiles/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Providers/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Organizations/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── ServiceCatalog/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Openings/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Booking/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Payments/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Refunds/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Payouts/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Notifications/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   ├── Admin/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Api/
│   │   └── AuditLogging/
│   │       ├── Domain/
│   │       ├── Application/
│   │       ├── Infrastructure/
│   │       └── Api/
│   ├── Platform/
│   │   ├── Runtime/
│   │   │   ├── Jobs/
│   │   │   ├── Queue/
│   │   │   ├── Scheduler/
│   │   │   └── Worker/
│   │   ├── Integrations/
│   │   │   ├── Stripe/
│   │   │   └── SSO/
│   │   ├── Webhooks/
│   │   │   ├── Ingress/
│   │   │   ├── Deduplication/
│   │   │   └── Dispatch/
│   │   ├── Idempotency/
│   │   ├── Outbox/
│   │   │   ├── Store/
│   │   │   └── Relay/
│   │   ├── Observability/
│   │   │   ├── Logging/
│   │   │   ├── Metrics/
│   │   │   └── Tracing/
│   │   └── Persistence/
│   │       ├── Database/
│   │       ├── Transactions/
│   │       └── Locking/
│   ├── Common/
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Api/
│   └── Bootstrap/
│       ├── Container/
│       ├── Routing/
│       └── ModuleRegistry/
└── tests/
    ├── Unit/
    ├── Integration/
    ├── Contract/
    └── E2E/
```

---

## 5) Responsibilities by module (corrected)

### 5.1 IdentityAccess

- **Purpose:** authorization, role mapping, actor/permission checks.
- **Owns:** `ActorContext`, role assignment, business permission guards.
- **Interfaces:** all protected module commands and API guards.
- **Out of scope:** registration, OTP, phone verification, credential lifecycle.

### 5.2 UserProfiles

- **Purpose:** local user profile data linked to upstream identity.
- **Owns:** user profile lifecycle and role-profile linkage.
- **Interfaces:** IdentityAccess, Providers, Booking, Admin.
- **Out of scope:** identity proofing.

### 5.3 Providers

- **Purpose:** provider lifecycle and operational state.
- **Owns:** provider aggregate and provider type (`individual`, `organization`).
- **Interfaces:** Organizations, ServiceCatalog, Openings, Admin.
- **Out of scope:** payout engine internals.

### 5.4 Organizations

- **Purpose:** organization entity and provider affiliation/membership.
- **Owns:** organization profile and organization membership relationships.
- **Interfaces:** Providers, IdentityAccess, Admin.
- **Out of scope:** non-business HR-like identity domains.

### 5.5 ServiceCatalog

- **Purpose:** provider offerings used by openings/booking.
- **Owns:** service offerings and baseline pricing/duration metadata.
- **Interfaces:** Providers, Openings, Booking.
- **Out of scope:** advanced promotion engines.

### 5.6 Openings

- **Purpose:** publishable last-minute openings/slots.
- **Owns:** opening lifecycle and reservable opening metadata.
- **Interfaces:** Providers, ServiceCatalog, Booking.
- **Out of scope:** complex long-term calendar optimization.

### 5.7 Booking

- **Purpose:** reservation and booking lifecycle.
- **Owns:** booking state machine, reservation concurrency rules, no-show policy for MVP.
- **Interfaces:** Openings, Payments, Refunds, Notifications, AuditLogging.
- **Out of scope:** direct payment gateway API wiring.

### 5.8 Payments

- **Purpose:** payment orchestration and payment state tracking.
- **Owns:** payment aggregate, payment attempts, external payment references.
- **Interfaces:** Booking, Refunds, platform Stripe integration, platform webhook ingress.
- **Out of scope:** payout scheduling policy.

### 5.9 Refunds

- **Purpose:** refund execution and refund state tracking.
- **Owns:** refund aggregate and refund decision records.
- **Interfaces:** Booking events (provider no-show), Payments, Notifications, AuditLogging.
- **Out of scope:** chargeback/dispute lifecycle.

### 5.10 Payouts

- **Purpose:** payout eligibility/scheduling/payment tracking.
- **Owns:** payout state and payout scheduling metadata.
- **Interfaces:** Booking, Payments, Refunds, Admin.
- **Out of scope:** advanced tax and cross-border compliance engines.

### 5.11 Notifications

- **Purpose:** domain-triggered message delivery.
- **Owns:** notification intents, templates, delivery attempts.
- **Interfaces:** Booking/Payments/Refunds/Admin via events and job dispatch.
- **Out of scope:** marketing automation platform.

### 5.12 Admin

- **Purpose:** internal admin and super-admin operations.
- **Owns:** admin provisioning flow and elevated operation orchestration.
- **Interfaces:** IdentityAccess, Providers, Organizations, Payouts, AuditLogging.
- **Out of scope:** external self-service admin signup.

### 5.13 AuditLogging

- **Purpose:** immutable trace of sensitive actions.
- **Owns:** append-only audit records and contextual metadata.
- **Interfaces:** all modules through append-only contract.
- **Out of scope:** replacing business events or analytics warehouse.

---

## 6) Layering and dependency direction (corrected)

## 6.1 Inside each business module

Preferred internal dependency direction:

`Api -> Application -> Domain`

`Infrastructure -> (Application + Domain contracts)`

Rules:

- Domain has no dependency on infrastructure or HTTP.
- Application orchestrates use cases and transaction boundaries.
- Infrastructure implements ports/adapters only.
- API handlers only translate I/O and invoke application layer.

## 6.2 Between business modules

- Cross-module integration uses explicit contracts, commands, and domain events/outbox.
- No direct writes to another module's private storage.
- No cyclic module dependencies.

## 6.3 Business modules vs platform/runtime

- Business modules depend on platform abstractions (idempotency service, queue, webhook dispatcher, outbox publisher) via contracts.
- Platform/runtime does not own business decisions; it executes technical capabilities.
- Webhook ingress routes into module application commands; it does not mutate business state directly.

---

## 7) MVP and extension points (locked)

## 7.1 MVP

- Provider publishes opening; client reserves/books/pays.
- Client cannot cancel.
- Client no-show treated as consumed service.
- Provider no-show triggers refund flow.
- Booking states include: `initiated`, `reserved`, `payment_pending`, `confirmed`, `completed`, `client_no_show`, `provider_no_show`, `cancelled_by_provider`, `reservation_expired`, `payment_failed`, `refunded`.
- Payment states include: `initiated`, `authorized`, `captured`, `failed`, `refunded`.
- Payout states include: `pending`, `eligible`, `scheduled`, `paid`.

## 7.2 Extension points

- `partial_refund` in payments/refunds.
- `blocked`/`reversed` in payouts.
- Chargebacks/disputes.
- Advanced pricing, advanced scheduling, advanced notification preferences.

---

## 8) ADR set (critical, locked)

1. **ADR-001:** Use modular monolith as the default architecture.
2. **ADR-002:** Keep authentication/registration in upstream SSO; keep authorization and business permissions local.
3. **ADR-003:** Use `ActorContext` as canonical local authenticated identity representation.
4. **ADR-004:** Enforce idempotency on critical writes.
5. **ADR-005:** Use transactional outbox for reliable async side effects.
6. **ADR-006:** Treat Stripe webhook ingestion as deduplicated, retry-safe, async-first platform flow.
7. **ADR-007:** Protect slot reservation with transactions + locking.
8. **ADR-008:** Keep provider foundation supporting both individual and organization models from start.
9. **ADR-009:** Keep no-show policy in Booking domain for MVP; emit events for Refunds/Notifications.
10. **ADR-010:** Keep standardized API error envelope across all modules.
