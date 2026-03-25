# Data integrity and safety

## Non-negotiable consistency rules

This section lists system truths that must never be violated.

1. A slot must not be actively booked by multiple clients at the same time.
2. A confirmed booking must have trustworthy payment truth.
3. Expired or failed booking attempts must stop blocking future booking attempts.
4. Refund actions must be traceable and must not duplicate accidentally.
5. User identity must remain globally trustworthy.
6. Provider actors must not mutate another provider's data.
7. Booking transitions must follow explicit allowed paths.
8. Payment transitions must follow explicit allowed paths.
9. Webhook processing must be deduplicated.
10. Critical actions must be auditable.
11. Idempotency must be part of the design, not a later patch.
12. Availability truth must be derived consistently.

### Rule details

#### 1. No double active booking for the same slot

The system must prevent multiple active bookings for the same slot. Booking creation must use transactional protection and a PostgreSQL uniqueness strategy appropriate for this invariant.

#### 2. Confirmed booking requires trusted payment truth

A booking must not become confirmed unless payment success is verified by backend-controlled logic. Frontend optimism is not sufficient.

#### 3. Failed or expired attempts must stop blocking

If a hold expires or payment fails, the booking must no longer behave as an active blocker for the slot.

#### 4. Refunds must be traceable and idempotent

Refunds must be idempotent. The same refund action must not happen multiple times because of retries, duplicate requests, or repeated webhooks.

#### 5. Global identity trust is mandatory

Phone-based identity must not allow duplicate fake users through loose verification rules. Phone uniqueness and OTP verification must be enforced consistently.

#### 6. Provider isolation is mandatory

A provider member may act only within the provider account they belong to and within their granted permission scope.

#### 7. Booking transitions are state-machine controlled

A booking must not jump between states arbitrarily. State machine rules are mandatory.

#### 8. Payment transitions are state-machine controlled

Payment status must be driven by verified backend and Stripe truth, not ad hoc assumptions.

#### 9. Webhook processing must be deduplicated

Stripe webhooks may be retried. The same external event must not produce repeated business effects.

#### 10. Critical actions must be auditable

Auth events, booking state changes, payment and refund actions, and admin manual actions must be traceable.

#### 11. Idempotency is a design requirement

Booking creation, payment initiation, webhook processing, refund execution, and critical admin actions must be designed with idempotency in mind.

#### 12. Availability truth must stay consistent

The system must not let slot state, booking state, and payment state drift into contradictory meanings. The architecture must preserve a single reliable interpretation of active booking occupancy.

## Implementation principle

If there is a tradeoff between short-term coding speed and preserving these rules, preserving these rules wins.

## Database constraints and locking rules

The database must enforce the following:

- Phone number must be unique at the identity level.
- Stripe webhook event IDs must be unique.
- Idempotency keys must be unique within their intended operation scope.
- Active booking duplication for the same slot must be blocked.
- Important booking creation must happen inside a database transaction.
- Booking creation must use locking or equivalent transactional safeguards.
- PostgreSQL features such as partial unique indexes should be used intentionally.
- Availability correctness must not depend on lucky request ordering.

## Idempotency strategy

The following actions must be idempotent:

- Booking creation
- Payment intent creation
- Refund creation
- Stripe webhook processing
- Critical admin actions where possible

The system should persist idempotency keys and operation results or operation references. Idempotency must be designed as part of the application contract, not added later as a patch.

## Webhook processing rules

Stripe webhook handling must follow these rules:

- Webhook events must be stored before being considered processed.
- Webhook event IDs must be deduplicated.
- Invalid or already-processed events must not trigger duplicate business actions.
- Handlers must not assume delivery happens only once.
- Processing should be traceable and retry-safe.
- Stripe success or failure signals should be the source of truth for payment outcome transitions.

## Hold expiry rules

Booking holds must expire in a controlled and repeatable way.

- A booking hold must have an explicit expiration timestamp.
- A background job must scan for expired holds.
- Expired holds must transition to `expired`.
- Expired or failed holds must stop blocking future booking attempts.
- Hold cleanup must be reliable and repeatable.
- Hold expiry actions should be auditable at least at an operational level.
