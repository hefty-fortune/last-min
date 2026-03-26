# Batch 2 — Domain Model and Database Schema Draft

## 0) Scope and guardrails

This batch defines:

- domain model by module
- aggregate boundaries
- entity relationships
- state machines
- relational schema draft
- migration ordering
- cross-cutting support structures (idempotency, webhooks, outbox, audit, jobs)

This batch does **not** define:

- OpenAPI contracts (Batch 3)
- starter code skeletons (Batch 4)

Canonical context:

- `docs/13-backend/00-locked-foundation-summary.md`
- `docs/13-backend/13-batch-1-architecture-foundation.md`

---

## 1) Domain model breakdown by module

## 1.1 IdentityAccess

**Purpose:** authorization boundary and local role mapping for upstream identity.

**Aggregate roots:**

- `ActorRoleAssignment`
- `PermissionGrant` (optional explicit table for admin overrides)

**Key entities/value objects:**

- `ActorContext` (runtime VO from upstream identity claims)
- `Role` (`client`, `provider`, `admin`, `super_admin`)
- `PermissionCode`

**Owns state/tables:** actor-role mapping and permission overrides only.

---

## 1.2 UserProfiles

**Purpose:** local user profile and identity linkage.

**Aggregate root:** `UserProfile`.

**Key entities/value objects:**

- `DisplayName`
- `ContactPreferences`
- `UserProfileStatus`

**Owns state/tables:** user local profile, optional locale/timezone/settings.

---

## 1.3 Providers

**Purpose:** provider business actor lifecycle.

**Aggregate root:** `Provider`.

**Key entities/value objects:**

- `ProviderType` (`individual`, `organization`)
- `ProviderStatus`
- `ProviderOwnership`

**Owns state/tables:** provider core row, provider owner linkage.

---

## 1.4 Organizations

**Purpose:** organization and membership structure for provider ownership/operation.

**Aggregate root:** `Organization`.

**Key entities/value objects:**

- `OrganizationMember`
- `OrganizationRole` (`owner`, `manager`, `staff`)
- `OrganizationStatus`

**Owns state/tables:** organizations, organization memberships.

---

## 1.5 ServiceCatalog

**Purpose:** provider-defined bookable offerings.

**Aggregate root:** `ServiceOffering`.

**Key entities/value objects:**

- `Money`
- `DurationMinutes`
- `ServiceVisibility`

**Owns state/tables:** offerings and baseline pricing/duration metadata.

---

## 1.6 Openings

**Purpose:** publishable, reservable last-minute slots.

**Aggregate root:** `Opening`.

**Key entities/value objects:**

- `OpeningWindow` (start/end)
- `OpeningStatus` (`draft`, `published`, `reserved`, `booked`, `expired`, `cancelled_by_provider`)
- `Capacity` (MVP default 1)

**Owns state/tables:** opening lifecycle and reservation-relevant attributes.

---

## 1.7 Booking

**Purpose:** reservation and booking lifecycle, including no-show policy.

**Aggregate root:** `Booking`.

**Key entities/value objects:**

- `BookingState`
- `ReservationLock`
- `NoShowClassification`

**Owns state/tables:** booking state, reservation timestamps/TTL, actor references.

**Important boundary:** Payments may reference booking IDs, but **Payments cannot own or mutate booking state directly**.

---

## 1.8 Payments

**Purpose:** payment lifecycle and external gateway references.

**Aggregate root:** `Payment`.

**Key entities/value objects:**

- `PaymentState`
- `PaymentMethodType`
- `StripePaymentIntentRef`

**Owns state/tables:** payment records, payment attempts, gateway references.

**Important boundary:** Refunds may reference payment IDs, but **Refunds cannot own payment state directly**.

---

## 1.9 Refunds

**Purpose:** refund lifecycle, especially provider no-show outcomes.

**Aggregate root:** `Refund`.

**Key entities/value objects:**

- `RefundState`
- `RefundReason`
- `RefundAmount`

**Owns state/tables:** refund records and reason metadata.

---

## 1.10 Payouts

**Purpose:** provider payout eligibility and payout execution status.

**Aggregate root:** `Payout`.

**Key entities/value objects:**

- `PayoutState`
- `PayoutWindow`
- `PayoutBlockReason` (extension point)

**Owns state/tables:** payout state and execution references.

---

## 1.11 Notifications

**Purpose:** notification intents and delivery tracking.

**Aggregate root:** `Notification`.

**Key entities/value objects:**

- `NotificationChannel`
- `NotificationTemplateCode`
- `DeliveryStatus`

**Owns state/tables:** notifications and delivery attempts.

---

## 1.12 Admin

**Purpose:** internal admin provisioning and sensitive operations.

**Aggregate roots:**

- `AdminAccount`
- `AdminAction`

**Owns state/tables:** admin account provisioning records, admin operation logs (business-facing).

---

## 1.13 AuditLogging (cross-cutting)

**Purpose:** append-only trace for sensitive operations.

**Aggregate root:** append-only `AuditLogEntry`.

**Rule:** no updates/deletes to audit rows in normal flow.

---

## 2) Major relationships (entity-level)

- `user_profiles.id` is the canonical relational user key.
- `user_profiles.identity_subject` stores upstream SSO subject/identity reference.
- A `provider` is linked to either:
  - one individual user profile (`owner_user_profile_id`) **or**
  - one organization (`organization_id`) via direct provider ownership.
- `service_offerings` belong to `providers`.
- `openings` belong to `providers` and reference one `service_offering`.
- `bookings` reference one `opening`, one `client_user_profile_id`, and one provider.
- `payments` reference one `booking` (1:1 in MVP).
- `refunds` reference one `payment` and optionally one `booking` for direct traceability.
- `payouts` reference one `provider`; payout items can reference bookings/payments.
- `notifications` reference business entity (`booking`, `payment`, `refund`, etc.) via typed resource fields.
- `audit_logs` capture actor + action + resource + correlation metadata for any sensitive operation.

---

## 3) State machines (locked for Batch 2)

## 3.1 Booking state machine

States:

- `initiated`
- `reserved`
- `payment_pending`
- `confirmed`
- `completed`
- `client_no_show`
- `provider_no_show`
- `cancelled_by_provider`
- `reservation_expired` (reservation TTL elapsed before confirmation)
- `payment_failed` (MVP pre-confirmation terminal outcome)
- `refunded`

Primary transitions:

- `initiated -> reserved` (application-level pre-persistence to persisted reservation)
- `reserved -> payment_pending` (payment intent created)
- `payment_pending -> confirmed` (payment captured/confirmed)
- `reserved|payment_pending -> reservation_expired` (reservation timeout before confirmation)
- `payment_pending -> payment_failed` (payment failure before confirmation)
- `confirmed -> completed` (service delivered)
- `confirmed -> client_no_show` (policy outcome)
- `confirmed -> provider_no_show` (policy outcome, triggers refund workflow)
- `provider_no_show -> refunded` (refund completed)
- `reserved|payment_pending -> cancelled_by_provider` (provider cancellation before confirmation rules)

Terminal states (MVP): `completed`, `client_no_show`, `refunded`, `cancelled_by_provider`, `reservation_expired`, `payment_failed`.

## 3.2 Payment state machine

States:

- `initiated`
- `authorized`
- `captured`
- `failed`
- `refunded`

Transitions:

- `initiated -> authorized`
- `authorized -> captured`
- `initiated|authorized -> failed`
- `captured -> refunded`

Extension point: `partial_refund`.

## 3.3 Refund state machine

States:

- `requested`
- `pending`
- `succeeded`
- `failed`
- `cancelled` (internal/admin flow only)

Transitions:

- `requested -> pending`
- `pending -> succeeded|failed`
- `requested|pending -> cancelled` (restricted)

## 3.4 Payout state machine

States:

- `pending`
- `eligible`
- `scheduled`
- `paid`

Transitions:

- `pending -> eligible`
- `eligible -> scheduled`
- `scheduled -> paid`

Extension points: `blocked`, `reversed`.

## 3.5 Opening state machine

States:

- `draft`
- `published`
- `reserved`
- `booked`
- `expired`
- `cancelled_by_provider`

Transitions:

- `draft -> published`
- `published -> reserved` (on booking reservation lock)
- `reserved -> booked` (booking confirmed)
- `reserved -> published` (reservation expiry before confirmation)
- `reserved -> published` (payment failure before confirmation)
- `published|reserved -> expired` (time-based)
- `published|reserved -> cancelled_by_provider` (policy-guarded)

---

## 4) Invariants and domain rules

## 4.1 Booking a slot / preventing double reservation

- One opening can have at most one active booking in MVP (`capacity=1`).
- In MVP, booking row creation starts at `reserved` (not `initiated`); `initiated` remains pre-persistence application state.
- If reservation TTL expires before confirmation, booking transitions to terminal `reservation_expired` and opening is reopened (`reserved -> published`).
- If pre-confirmation payment fails, booking transitions to terminal `payment_failed` and opening is reopened (`reserved -> published`).
- `bookings` must enforce one active booking per opening via unique/partial uniqueness strategy.
- Reservation transition must run inside transaction with row lock on opening (`SELECT ... FOR UPDATE` equivalent).
- Reservation includes expiry timestamp (`reservation_expires_at`); expired reservations can be reclaimed safely.

## 4.2 Payment confirmation

- Booking cannot become `confirmed` unless payment is `captured` (or equivalent confirmed event).
- Duplicate payment callbacks must be idempotent and cannot produce duplicate booking transitions.

## 4.3 Refund on provider no-show

- `booking.state = provider_no_show` mandates refund request creation.
- Refund amount defaults to full captured client amount in MVP.
- Booking transitions to `refunded` only after refund `succeeded`.
- MVP refund cardinality: one full refund per payment; recommend unique constraint on `refunds.payment_id`.

## 4.4 Client no-show treatment

- `booking.state = client_no_show` is terminal and treated as consumed service.
- No client cancellation path is available in MVP.

## 4.5 Individual vs organization provider ownership

- Provider must be exactly one ownership model at a time:
  - individual owner user, or
  - organization-owned provider.
- Enforce with DB check/constraint + service-level invariant.

## 4.6 Audit-sensitive operations

Audit logs required for at least:

- admin account provisioning and role changes
- provider ownership/status changes
- opening publication/unpublication/cancellation
- booking no-show decisions
- payment/refund/payout state overrides or manual interventions

---

## 5) Relational database schema draft

> Types shown are PostgreSQL-oriented and portable with minor adaptation.

## 5.1 IdentityAccess + UserProfiles + Admin

### `user_profiles`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| identity_subject | varchar(191) | no | upstream SSO subject/identity reference |
| email | varchar(320) | yes | optional local cache |
| phone_e164 | varchar(32) | yes | optional local cache |
| display_name | varchar(200) | yes |  |
| locale | varchar(16) | yes |  |
| timezone | varchar(64) | yes |  |
| status | varchar(32) | no | active/suspended/etc |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- PK `(id)`
- UNIQUE `(identity_subject)`
- INDEX `(status)`

### `actor_role_assignments`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| user_profile_id | uuid | no | FK -> user_profiles.id |
| role_code | varchar(32) | no | client/provider/admin/super_admin |
| assigned_by_user_profile_id | uuid | yes | admin actor |
| assigned_at | timestamptz | no |  |
| revoked_at | timestamptz | yes | null means active |

Constraints/indexes:

- PARTIAL UNIQUE active-role index: `(user_profile_id, role_code)` where `revoked_at IS NULL`
- INDEX `(role_code)`

### `admin_accounts`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| user_profile_id | uuid | no | unique admin identity; FK -> user_profiles.id |
| admin_type | varchar(32) | no | admin/super_admin |
| status | varchar(32) | no | active/inactive |
| provisioned_by_user_profile_id | uuid | no | internal actor; FK -> user_profiles.id |
| provisioned_at | timestamptz | no |  |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- UNIQUE `(user_profile_id)`
- INDEX `(admin_type, status)`

---

## 5.2 Providers + Organizations

### `organizations`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| legal_name | varchar(255) | no |  |
| display_name | varchar(255) | yes |  |
| status | varchar(32) | no | active/suspended |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Indexes: `(status)`.

### `organization_members`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| organization_id | uuid | no | FK -> organizations.id |
| user_profile_id | uuid | no | member user; FK -> user_profiles.id |
| role_code | varchar(32) | no | owner/manager/staff |
| status | varchar(32) | no | active/inactive |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- UNIQUE `(organization_id, user_profile_id)`
- INDEX `(organization_id, role_code)`

### `providers`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| provider_type | varchar(32) | no | individual/organization |
| owner_user_profile_id | uuid | yes | required if individual; FK -> user_profiles.id |
| organization_id | uuid | yes | required if organization |
| status | varchar(32) | no | onboarding/active/suspended |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- CHECK exactly one ownership mode:
  - `(provider_type='individual' AND owner_user_profile_id IS NOT NULL AND organization_id IS NULL)` OR
  - `(provider_type='organization' AND organization_id IS NOT NULL AND owner_user_profile_id IS NULL)`
- UNIQUE `(owner_user_profile_id)` where individual active ownership uniqueness is desired
- INDEX `(organization_id)`
- INDEX `(provider_type, status)`

Soft delete note: use `status` and `suspended_at` (optional) instead of hard delete.

---

## 5.3 ServiceCatalog + Openings

### `service_offerings`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| provider_id | uuid | no | FK -> providers.id |
| name | varchar(255) | no |  |
| description | text | yes |  |
| duration_minutes | integer | no | >0 |
| price_amount | bigint | no | minor units |
| price_currency | char(3) | no | ISO code |
| status | varchar(32) | no | active/inactive |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- CHECK `(duration_minutes > 0)`
- CHECK `(price_amount >= 0)`
- INDEX `(provider_id, status)`

### `openings`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| provider_id | uuid | no | FK -> providers.id |
| service_offering_id | uuid | no | FK -> service_offerings.id |
| starts_at | timestamptz | no |  |
| ends_at | timestamptz | no |  |
| timezone | varchar(64) | no | display context |
| capacity | integer | no | MVP default 1 |
| status | varchar(32) | no | draft/published/reserved/booked/expired/cancelled_by_provider |
| published_at | timestamptz | yes |  |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- CHECK `(ends_at > starts_at)`
- CHECK `(capacity > 0)`
- INDEX `(provider_id, starts_at)`
- INDEX `(status, starts_at)`
- Optional UNIQUE `(provider_id, starts_at, ends_at, service_offering_id)` for duplication safety.

Locking note: reservation flow locks target opening row.

---

## 5.4 Booking

### `bookings`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| opening_id | uuid | no | FK -> openings.id |
| provider_id | uuid | no | FK -> providers.id |
| client_user_profile_id | uuid | no | FK -> user_profiles.id |
| state | varchar(32) | no | booking state machine |
| reservation_expires_at | timestamptz | yes | used for reserved/payment_pending timeouts |
| payment_required_amount | bigint | no | snapshot |
| payment_currency | char(3) | no | snapshot |
| no_show_actor | varchar(32) | yes | client/provider |
| no_show_recorded_at | timestamptz | yes |  |
| confirmed_at | timestamptz | yes |  |
| completed_at | timestamptz | yes |  |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- CHECK `(payment_required_amount >= 0)`
- CHECK no-show consistency when `state IN ('client_no_show','provider_no_show')`
- INDEX `(opening_id)`
- INDEX `(client_user_profile_id, created_at)`
- INDEX `(provider_id, created_at)`
- INDEX `(state, reservation_expires_at)`
- UNIQUE active booking per opening (implementation choice):
  - partial unique on `opening_id` where `state IN ('reserved','payment_pending','confirmed')`
  - terminal pre-confirmation states (`reservation_expired`, `payment_failed`) are excluded so a new reservation can be created for the same opening

Soft delete: **hard delete discouraged**; retain for finance/audit traceability.

---

## 5.5 Payments + Refunds + Payouts

### `payments`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| booking_id | uuid | no | FK -> bookings.id |
| provider_id | uuid | no | FK -> providers.id |
| client_user_profile_id | uuid | no | payer; FK -> user_profiles.id |
| state | varchar(32) | no | initiated/authorized/captured/failed/refunded |
| amount | bigint | no | minor units |
| currency | char(3) | no | ISO code |
| stripe_payment_intent_id | varchar(128) | yes | unique when present |
| captured_at | timestamptz | yes |  |
| failed_reason | varchar(255) | yes |  |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- UNIQUE `(booking_id)` (MVP 1:1)
- UNIQUE `(stripe_payment_intent_id)`
- INDEX `(state, created_at)`
- INDEX `(provider_id, state)`

### `payment_attempts`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| payment_id | uuid | no | FK -> payments.id |
| attempt_no | integer | no | monotonic per payment |
| status | varchar(32) | no | initiated/succeeded/failed |
| provider_ref | varchar(128) | yes | gateway ref |
| raw_response | jsonb | yes | redacted data only |
| created_at | timestamptz | no |  |

Constraints/indexes:

- UNIQUE `(payment_id, attempt_no)`
- INDEX `(payment_id, created_at)`

### `refunds`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| payment_id | uuid | no | FK -> payments.id |
| booking_id | uuid | yes | FK -> bookings.id |
| state | varchar(32) | no | requested/pending/succeeded/failed/cancelled |
| reason_code | varchar(64) | no | provider_no_show/manual/etc |
| amount | bigint | no | minor units |
| currency | char(3) | no | ISO code |
| stripe_refund_id | varchar(128) | yes | unique when present |
| requested_by_user_profile_id | uuid | yes | null for system workflow; FK -> user_profiles.id |
| processed_at | timestamptz | yes |  |
| failure_reason | varchar(255) | yes |  |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- UNIQUE `(stripe_refund_id)`
- UNIQUE `(payment_id)` (recommended for MVP one-full-refund-per-payment rule)
- INDEX `(payment_id, state)`
- INDEX `(booking_id)`
- CHECK `(amount > 0)`

### `payouts`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| provider_id | uuid | no | FK -> providers.id |
| state | varchar(32) | no | pending/eligible/scheduled/paid |
| period_start | timestamptz | no | settlement window |
| period_end | timestamptz | no | settlement window |
| gross_amount | bigint | no |  |
| refund_amount | bigint | no |  |
| net_amount | bigint | no |  |
| currency | char(3) | no | ISO code |
| scheduled_at | timestamptz | yes |  |
| paid_at | timestamptz | yes |  |
| payout_reference | varchar(128) | yes | external ref |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- CHECK `(period_end > period_start)`
- CHECK `(net_amount = gross_amount - refund_amount)`
- INDEX `(provider_id, state)`
- INDEX `(state, scheduled_at)`

### `payout_items`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| payout_id | uuid | no | FK -> payouts.id |
| provider_id | uuid | no | FK -> providers.id |
| booking_id | uuid | yes | FK -> bookings.id |
| payment_id | uuid | yes | FK -> payments.id |
| gross_amount | bigint | no | minor units |
| refund_amount | bigint | no | minor units |
| net_amount | bigint | no | derived payout line amount |
| currency | char(3) | no | ISO code |
| created_at | timestamptz | no |  |

Constraints/indexes:

- PK `(id)`
- CHECK `(gross_amount >= 0)`
- CHECK `(refund_amount >= 0)`
- CHECK `(net_amount = gross_amount - refund_amount)`
- INDEX `(payout_id)`
- INDEX `(provider_id, booking_id)`
- INDEX `(provider_id, payment_id)`
- UNIQUE `(payout_id, booking_id)` where `booking_id IS NOT NULL` (prevents duplicate booking lines inside one payout)

---

## 5.6 Notifications

### `notifications`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| recipient_user_profile_id | uuid | no | target actor; FK -> user_profiles.id |
| channel | varchar(32) | no | email/sms/push/in_app |
| template_code | varchar(64) | no |  |
| resource_type | varchar(64) | no | booking/payment/refund/... |
| resource_id | uuid | no | referenced resource |
| payload | jsonb | no | template vars |
| status | varchar(32) | no | pending/sent/failed |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Indexes:

- INDEX `(recipient_user_profile_id, created_at)`
- INDEX `(status, created_at)`

### `notification_deliveries`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| notification_id | uuid | no | FK -> notifications.id |
| attempt_no | integer | no |  |
| status | varchar(32) | no | sent/failed |
| provider_message_id | varchar(128) | yes |  |
| error_code | varchar(64) | yes |  |
| created_at | timestamptz | no |  |

Constraints/indexes:

- UNIQUE `(notification_id, attempt_no)`
- INDEX `(notification_id, created_at)`

---

## 5.7 Cross-cutting support tables (platform/runtime)

> These support reliability/operations and do **not** replace module-owned business tables.

### `idempotency_keys`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| scope | varchar(64) | no | e.g., booking.reserve |
| idempotency_key | varchar(128) | no | client-provided key |
| request_hash | varchar(128) | no | payload fingerprint |
| response_code | integer | yes | stored response status |
| response_body | jsonb | yes | stored safe response |
| resource_type | varchar(64) | yes | booking/payment/etc |
| resource_id | uuid | yes | resultant resource |
| locked_until | timestamptz | yes | short lock window |
| expires_at | timestamptz | no | retention boundary |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Constraints/indexes:

- UNIQUE `(scope, idempotency_key)`
- INDEX `(expires_at)`

### `stripe_webhook_events`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| stripe_event_id | varchar(128) | no | unique dedup key |
| event_type | varchar(128) | no |  |
| payload | jsonb | no | raw webhook payload |
| signature_valid | boolean | no |  |
| processing_state | varchar(32) | no | received/processing/processed/failed |
| first_received_at | timestamptz | no |  |
| last_received_at | timestamptz | no |  |
| processed_at | timestamptz | yes |  |
| failure_reason | text | yes |  |

Constraints/indexes:

- UNIQUE `(stripe_event_id)`
- INDEX `(processing_state, first_received_at)`
- INDEX `(event_type)`

### `outbox_messages`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| aggregate_type | varchar(64) | no | booking/payment/refund/... |
| aggregate_id | uuid | no | source aggregate |
| event_type | varchar(128) | no | domain event |
| payload | jsonb | no | event payload |
| occurred_at | timestamptz | no | domain event time |
| published_at | timestamptz | yes | null = pending |
| publish_attempts | integer | no | default 0 |
| last_error | text | yes |  |
| created_at | timestamptz | no |  |

Indexes:

- INDEX `(published_at, created_at)`
- INDEX `(aggregate_type, aggregate_id)`

### `audit_logs` (append-only)

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| occurred_at | timestamptz | no | immutable event time |
| actor_user_profile_id | uuid | yes | null for system events; FK -> user_profiles.id |
| actor_role | varchar(32) | yes |  |
| action_code | varchar(128) | no | e.g., booking.provider_no_show_recorded |
| resource_type | varchar(64) | no |  |
| resource_id | uuid | no |  |
| correlation_id | varchar(128) | yes | request/job correlation |
| metadata | jsonb | yes | redacted, non-PII where possible |

Indexes:

- INDEX `(resource_type, resource_id, occurred_at)`
- INDEX `(actor_user_profile_id, occurred_at)`
- INDEX `(action_code, occurred_at)`

### `jobs`

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| queue_name | varchar(64) | no |  |
| job_type | varchar(128) | no |  |
| payload | jsonb | no |  |
| state | varchar(32) | no | queued/running/succeeded/failed/dead |
| available_at | timestamptz | no |  |
| locked_at | timestamptz | yes |  |
| locked_by | varchar(128) | yes | worker id |
| attempts | integer | no | default 0 |
| max_attempts | integer | no |  |
| correlation_id | varchar(128) | yes |  |
| created_at | timestamptz | no |  |
| updated_at | timestamptz | no |  |

Indexes:

- INDEX `(queue_name, state, available_at)`
- INDEX `(state, updated_at)`

### `job_runs` (optional operational history)

| Column | Type | Null | Notes |
|---|---|---:|---|
| id | uuid | no | PK |
| job_id | uuid | no | FK -> jobs.id |
| run_no | integer | no |  |
| started_at | timestamptz | no |  |
| finished_at | timestamptz | yes |  |
| outcome | varchar(32) | no | succeeded/failed |
| error_message | text | yes |  |

Constraints/indexes:

- UNIQUE `(job_id, run_no)`
- INDEX `(job_id, started_at)`

---

## 6) Transaction and locking notes (critical)

1. **Reserve opening + create booking**: single transaction.
   - lock opening row
   - validate `openings.status='published'` and time validity
   - enforce single active booking invariant
   - insert booking as `reserved`
   - transition opening to `reserved`
   - insert outbox message
   - if reservation expires before confirmation, timeout job transitions booking to `reservation_expired` and opening `reserved -> published` in one transaction

2. **Payment confirmation flow**: idempotent transition guard.
   - process payment event once (idempotency/webhook dedup)
   - transition payment state
   - transition booking `payment_pending -> confirmed`
   - opening `reserved -> booked`
   - write outbox + audit entries
   - on payment failure before confirmation, transition booking `payment_pending -> payment_failed` and opening `reserved -> published`

3. **Provider no-show flow**: transactionally create refund request.
   - booking `confirmed -> provider_no_show`
   - create refund `requested`
   - emit refund_requested event/outbox

4. **Refund completion flow**:
   - refund `pending -> succeeded`
   - booking `provider_no_show -> refunded`

---

## 7) Soft delete vs hard delete guidance

- **Hard delete discouraged** for: bookings, payments, refunds, payouts, audit_logs, webhook events, idempotency keys (until retention expiry), outbox, jobs history.
- Prefer status-based deactivation for: providers, organizations, service_offerings, admin_accounts.
- If legal deletion is required for profile fields, perform selective PII anonymization while preserving financial/audit references.

---

## 8) Migration ordering recommendation

1. **Foundations**
   - create enum/lookup strategy (or check constraints), extension utilities, timestamps conventions.
2. **Identity/admin core**
   - `user_profiles`, `actor_role_assignments`, `admin_accounts`.
3. **Organization/provider core**
   - `organizations`, `organization_members`, `providers`.
4. **Catalog/openings**
   - `service_offerings`, `openings`.
5. **Booking/payments/refunds/payouts**
   - `bookings`, `payments`, `payment_attempts`, `refunds`, `payouts`, `payout_items`.
6. **Notifications**
   - `notifications`, `notification_deliveries`.
7. **Cross-cutting reliability**
   - `idempotency_keys`, `stripe_webhook_events`, `outbox_messages`, `audit_logs`, `jobs`, `job_runs`.
8. **Add partial indexes and advanced constraints**
   - active booking uniqueness per opening
   - webhook dedup uniqueness
   - outbox pending publication index.

---

## 9) Assumptions and extension points

## 9.1 Assumptions

- PostgreSQL is the baseline relational DB.
- Monetary values stored in minor units (`bigint`) with ISO currency.
- UUID primary keys across business and support tables.
- Capacity is 1 in MVP, but schema leaves room for >1.
- Upstream identity is trusted at ingress; local authz uses `ActorContext`.

## 9.2 Extension points

- payment partial refunds
- payout blocked/reversed states
- chargeback/dispute subdomain
- richer provider compliance/tax structures
- notification preference center and multichannel escalation
