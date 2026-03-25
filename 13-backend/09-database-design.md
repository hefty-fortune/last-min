# Database design

## Initial table list

The initial backend foundation includes the following tables:

- `users`
- `user_phones`
- `otp_challenges`
- `user_roles`
- `refresh_tokens`
- `user_sessions`
- `provider_accounts`
- `provider_members`
- `provider_locations`
- `provider_staff`
- `provider_services`
- `provider_resources`
- `slots`
- `bookings`
- `payments`
- `refunds`
- `webhook_events`
- `outbox_messages`
- `audit_logs`

## Entity notes

- `users` stores global user identity.
- `user_phones` stores phone values and verification status.
- `otp_challenges` stores verification attempts and lockout context.
- `user_roles` stores global or system-level roles.
- `refresh_tokens` stores revocable refresh credentials.
- `user_sessions` stores session or device traceability.
- `provider_accounts` stores provider business identity.
- `provider_members` links users to provider accounts with operational roles.
- `provider_locations` stores provider locations.
- `provider_staff` stores bookable or operational staff members.
- `provider_services` stores what clients can book.
- `provider_resources` stores optional allocatable physical or logical resources.
- `slots` stores published offerable times.
- `bookings` stores reservation lifecycle.
- `payments` stores payment lifecycle.
- `refunds` stores refund lifecycle.
- `webhook_events` stores inbound Stripe event processing.
- `outbox_messages` stores asynchronous side effects or events.
- `audit_logs` stores who changed what and when.

## Migration strategy

### Design principle

Migrations are not only technical scripts. They are part of the system's architecture history. Each migration should make one clear schema change, be easy to review, and be safe to run in a predictable deployment flow.

### Core objectives

- Keep schema evolution explicit
- Avoid manual database drift
- Support safe local, test, and production rollout
- Make review of schema changes easier
- Protect business-critical tables from careless modification
- Help junior developers change the database in a controlled way

### Migration rules

1. All schema changes must be migration-driven.
2. Migrations must be committed with the related code change.
3. Each migration should have a narrow purpose.
4. Migrations must be readable.
5. Destructive changes require extra care.
6. Production data history matters.
7. PostgreSQL features should be used intentionally.

### Recommended migration structure

Build the schema in layers that follow domain dependency order:

1. Identity and auth
2. Provider structure
3. Slot and booking core
4. Payments and refunds
5. Reliability and audit
6. Optimizations and refinements

### Recommended first migration batches

#### Batch 1 - identity foundation

Create:

- `users`
- `user_phones`
- `otp_challenges`
- `user_roles`
- `refresh_tokens`
- `user_sessions`

#### Batch 2 - provider foundation

Create:

- `provider_accounts`
- `provider_members`
- `provider_locations`
- `provider_staff`
- `provider_services`
- `provider_resources`

#### Batch 3 - supply and booking foundation

Create:

- `slots`
- `bookings`

#### Batch 4 - payment foundation

Create:

- `payments`
- `refunds`

#### Batch 5 - reliability and traceability foundation

Create:

- `webhook_events`
- `outbox_messages`
- `audit_logs`

#### Batch 6 - protection and performance

Add:

- Indexes
- Unique constraints
- Check constraints
- Partial unique indexes
- Later optimization indexes based on real query patterns

### Recommended migration phases

#### Phase 1 - create tables with core columns

Create the main tables with primary keys, foreign keys, status fields, timestamps, and minimum business columns.

#### Phase 2 - add non-negotiable constraints

Add:

- Phone uniqueness
- Provider membership uniqueness
- Stripe event uniqueness
- Stripe payment and refund uniqueness
- Booking active-state uniqueness
- Status validation constraints

#### Phase 3 - add operational indexes

Add indexes for:

- Slot listing
- Booking lookup
- Provider ownership queries
- Webhook processing
- Outbox polling
- Audit filtering

#### Phase 4 - add refinements

Add smaller columns, supporting indexes, or optional structures only when justified by real feature work.

### Migration naming recommendation

Use a timestamp or ordered prefix plus a clear action description.

Examples:

- `20260325_001_create_users_table`
- `20260325_002_create_user_phones_table`
- `20260325_003_create_provider_accounts_table`
- `20260325_010_create_slots_table`
- `20260325_011_create_bookings_table`
- `20260325_020_add_active_booking_unique_index`
- `20260325_021_add_webhook_event_unique_constraint`

### Recommended implementation style

- Create before constrain when that keeps early migrations easier to review.
- Prefer additive changes where possible.
- Be careful with backfills.
- Keep business-critical migrations small.

### Recommended rollback philosophy

Do not assume every migration should always be rolled back mechanically in production. For some changes, especially destructive or data-transforming ones, a forward-fix migration is safer than rollback.

Recommended approach:

- Allow rollback in local and test where practical.
- Prefer forward corrective migrations in shared and production environments.
- Treat rollback of financial and booking schema changes with extra caution.

### Environment strategy

#### Local environment

Developers should be able to rebuild the schema from zero using the full migration chain.

#### Test environment

Test and staging should run migrations automatically or predictably as part of deployment or testing flow.

#### Production environment

Production migrations should be applied in a controlled deployment step with logging and clear visibility. High-risk migrations should be reviewed before execution.

### Schema drift policy

Schema drift between environments is not acceptable. If production differs from committed migration history, that is a problem to fix immediately.

### Data-sensitive migration policy

#### For bookings

Do not casually rewrite booking lifecycle columns or status meaning without architectural review.

#### For payments

Do not remove or alter processor reference columns without understanding reconciliation impact.

#### For refunds

Refund history must remain intact and traceable.

#### For webhook events

Deduplication guarantees must not be weakened casually.

#### For audit logs

Audit retention and integrity should be treated as platform trust concerns.

### Recommended migration review checklist

For each migration, review:

- What business need caused this migration
- Which table or invariant is changing
- Whether the change is additive or destructive
- Whether it affects bookings, payments, refunds, auth, or audit
- Whether foreign keys are correct
- Whether constraints are sufficient
- Whether indexes are justified
- Whether it creates risk for existing data
- Whether it requires a backfill
- Whether it requires application code coordination

### Recommended early migration backlog

1. Create `users`
2. Create `user_phones`
3. Create `otp_challenges`
4. Create `user_roles`
5. Create `refresh_tokens`
6. Create `user_sessions`
7. Create `provider_accounts`
8. Create `provider_members`
9. Create `provider_locations`
10. Create `provider_staff`
11. Create `provider_services`
12. Create `provider_resources`
13. Create `slots`
14. Create `bookings`
15. Create `payments`
16. Create `refunds`
17. Create `webhook_events`
18. Create `outbox_messages`
19. Create `audit_logs`
20. Add phone uniqueness
21. Add provider membership uniqueness
22. Add Stripe uniqueness constraints
23. Add active booking partial unique index
24. Add booking, payment, and refund status validation
25. Add operational indexes

### Recommended documentation rule

Every migration that changes an important invariant should also be reflected in the relevant architecture page if it changes system truth.

Examples:

- Changing booking active states
- Changing payment lifecycle states
- Changing provider ownership rules
- Changing identity uniqueness rules

## Initial key columns

These are foundation columns rather than a final exhaustive schema. They should be sufficient to support correct domain ownership, lifecycle tracking, safe foreign keys, operational visibility, and future extension.

### General conventions

- Every main table should have a primary key column named `id` unless there is a strong reason not to.
- Every main business table should have `created_at` and `updated_at`.
- Soft delete columns should not be added automatically everywhere.
- Status columns should be explicit where lifecycle matters.
- External integration references should be stored explicitly.
- IDs may be UUIDs or bigint, but the choice should be consistent across the system.

### 1. Identity and auth

#### users

Key columns:

- `id`
- `first_name`
- `last_name`
- `display_name`
- `email`
- `status`
- `created_at`
- `updated_at`

Notes:

- This is the global identity table.
- Keep it clean and generic.
- Do not put provider-specific business structure here.

#### user_phones

Key columns:

- `id`
- `user_id`
- `phone_number`
- `is_verified`
- `verified_at`
- `is_primary`
- `created_at`
- `updated_at`

Notes:

- `phone_number` should support normalized storage format.
- Global uniqueness rules are defined by constraints.

#### otp_challenges

Key columns:

- `id`
- `phone_number`
- `purpose`
- `otp_code_hash`
- `expires_at`
- `attempts_count`
- `max_attempts`
- `resend_available_at`
- `locked_until`
- `verified_at`
- `created_at`
- `updated_at`

Notes:

- Store hashed OTP rather than plain OTP where possible.
- `purpose` can later distinguish registration, login verification, or similar flows.

#### user_roles

Key columns:

- `id`
- `user_id`
- `role_code`
- `created_at`

Notes:

- Example role codes: `client`, `admin`, `super_admin`.
- Provider-scoped permissions should not rely only on this table.

#### refresh_tokens

Key columns:

- `id`
- `user_id`
- `token_hash`
- `expires_at`
- `revoked_at`
- `created_at`
- `updated_at`

Notes:

- Store token hash, not raw token.
- Rotation strategy can be added later.

#### user_sessions

Key columns:

- `id`
- `user_id`
- `refresh_token_id`
- `ip_address`
- `user_agent`
- `last_seen_at`
- `revoked_at`
- `created_at`
- `updated_at`

Notes:

- Useful for session visibility and audit traceability.

### 2. Provider structure

#### provider_accounts

Key columns:

- `id`
- `provider_type`
- `legal_name`
- `display_name`
- `status`
- `created_by_user_id`
- `created_at`
- `updated_at`

Notes:

- `provider_type` can be `individual` or `organization`.
- `legal_name` and `display_name` can diverge.
- `status` supports operational control.

#### provider_members

Key columns:

- `id`
- `provider_account_id`
- `user_id`
- `membership_role`
- `status`
- `created_at`
- `updated_at`

Notes:

- This is the core ownership and authorization link for provider-side actions.
- Membership roles might include `owner`, `manager`, `staff_admin`, or `staff`.

#### provider_locations

Key columns:

- `id`
- `provider_account_id`
- `name`
- `address_line_1`
- `address_line_2`
- `city`
- `postal_code`
- `country_code`
- `status`
- `created_at`
- `updated_at`

Notes:

- Keep address structure practical rather than overcomplicated.

#### provider_staff

Key columns:

- `id`
- `provider_account_id`
- `user_id`
- `display_name`
- `status`
- `created_at`
- `updated_at`

Notes:

- `user_id` may be nullable if some staff are modeled operationally before full user linkage.
- Decide later whether all staff must be global users.

#### provider_services

Key columns:

- `id`
- `provider_account_id`
- `name`
- `description`
- `duration_minutes`
- `default_price_amount`
- `currency_code`
- `status`
- `created_at`
- `updated_at`

Notes:

- Slots may snapshot price and service details at booking time if needed.

#### provider_resources

Key columns:

- `id`
- `provider_account_id`
- `name`
- `resource_type`
- `status`
- `created_at`
- `updated_at`

Notes:

- Optional in the MVP, but useful for extensibility.

### 3. Slot and booking core

#### slots

Key columns:

- `id`
- `provider_account_id`
- `provider_location_id`
- `provider_staff_id`
- `provider_service_id`
- `provider_resource_id`
- `start_at`
- `end_at`
- `price_amount`
- `currency_code`
- `status`
- `published_at`
- `cancelled_at`
- `created_by_user_id`
- `created_at`
- `updated_at`

Notes:

- `provider_resource_id` may be nullable.
- `provider_location_id` and `provider_staff_id` may be nullable only if the business truly allows it.
- Example statuses: `draft`, `published`, `unpublished`, `cancelled`.

#### bookings

Key columns:

- `id`
- `slot_id`
- `provider_account_id`
- `client_user_id`
- `status`
- `hold_expires_at`
- `confirmed_at`
- `completed_at`
- `provider_cancelled_at`
- `provider_no_show_at`
- `client_no_show_at`
- `expired_at`
- `payment_failed_at`
- `created_at`
- `updated_at`

Recommended snapshot columns:

- `booked_price_amount`
- `booked_currency_code`
- `booked_service_name`
- `booked_location_name`
- `booked_staff_name`

Notes:

- Snapshot fields preserve business truth even if provider configuration changes later.
- This table is central and should be designed very intentionally.

### 4. Payments and refunds

#### payments

Key columns:

- `id`
- `booking_id`
- `provider_account_id`
- `payment_provider`
- `status`
- `amount`
- `currency_code`
- `stripe_payment_intent_id`
- `stripe_charge_id`
- `stripe_connected_account_id`
- `succeeded_at`
- `failed_at`
- `cancelled_at`
- `created_at`
- `updated_at`

Notes:

- Keep the model ready for both platform and connected-account flows.
- `payment_provider` will likely be `stripe` in the MVP, but explicit storage is still useful.

#### refunds

Key columns:

- `id`
- `payment_id`
- `booking_id`
- `reason_code`
- `status`
- `amount`
- `currency_code`
- `stripe_refund_id`
- `requested_by_user_id`
- `approved_by_user_id`
- `succeeded_at`
- `failed_at`
- `created_at`
- `updated_at`

Notes:

- Example reasons: `provider_cancelled`, `provider_no_show`, `admin_manual`, `payment_error`, `other`.
- `approved_by_user_id` may remain nullable depending on automation flow.

### 5. Integration reliability

#### webhook_events

Key columns:

- `id`
- `provider_name`
- `external_event_id`
- `event_type`
- `payload_json`
- `received_at`
- `processed_at`
- `processing_status`
- `error_message`
- `created_at`
- `updated_at`

Notes:

- `provider_name` will likely be `stripe` in the MVP.
- `external_event_id` must later be unique per provider source.

#### outbox_messages

Key columns:

- `id`
- `topic`
- `aggregate_type`
- `aggregate_id`
- `payload_json`
- `status`
- `available_at`
- `processed_at`
- `error_message`
- `attempts_count`
- `created_at`
- `updated_at`

Notes:

- Used for async follow-up work such as emails and later reliable event delivery.

### 6. Audit and operations

#### audit_logs

Key columns:

- `id`
- `actor_user_id`
- `actor_role`
- `entity_type`
- `entity_id`
- `action_code`
- `before_json`
- `after_json`
- `request_id`
- `created_at`

Notes:

- `request_id` helps connect audit entries with application logs.
- `before_json` and `after_json` may be nullable depending on action type.

### Column philosophy notes

#### Timestamps

- `created_at` should exist almost everywhere.
- `updated_at` should exist on mutable records.
- Event-specific timestamps such as `confirmed_at`, `failed_at`, or `refunded_at` are often better than inferring everything from `updated_at`.

#### Status columns

- Use explicit status columns where lifecycle matters.
- Do not try to infer lifecycle entirely from nullable timestamps.

#### Money columns

- Use `amount` plus `currency_code` consistently.
- Decide early whether `amount` is stored as integer minor units or decimal numeric, then stay consistent.

#### Snapshot columns

- Snapshot columns are useful in bookings because provider-side configuration may change later.
- The booking must preserve what was actually purchased at booking time.

#### External references

- Stripe references should be stored explicitly and never only in logs.
- Webhook event identity must be persisted explicitly.

## Initial foreign keys

### Design principle

Foreign keys should express real domain ownership and protect the system from orphaned or invalid records. Delete behavior should be chosen intentionally. If historical and financial traceability matter, hard cascades should be used carefully.

### General rules

- Every foreign key should represent a real domain relationship.
- Financial, audit, and integration records should generally not disappear through aggressive cascade deletion.
- Core business history should be preserved wherever possible.
- Delete behavior should be explicit and not left to accidental defaults.
- Provider ownership and booking or payment relationships must be especially strict.

### 1. Identity and auth foreign keys

#### user_phones.user_id -> users.id

Meaning: a phone record belongs to one global user.

Recommended delete behavior: `RESTRICT`, or `CASCADE` only if deleting a user is a rare and intentional hard-delete operation. In practice, `RESTRICT` is safer for auditability.

#### otp_challenges

No required foreign key to `users` in the first version if OTP is tied primarily to `phone_number` during pre-user verification flow.

Optional future direction: add `user_id` later if OTP challenges are tied to authenticated user actions.

#### user_roles.user_id -> users.id

Meaning: a global role assignment belongs to one user.

Recommended delete behavior: `RESTRICT`.

#### refresh_tokens.user_id -> users.id

Meaning: a refresh token belongs to one user.

Recommended delete behavior: `RESTRICT`.

#### user_sessions.user_id -> users.id

Meaning: a session belongs to one user.

Recommended delete behavior: `RESTRICT`.

#### user_sessions.refresh_token_id -> refresh_tokens.id

Meaning: a session may reference the refresh token that created or maintains it.

Recommended delete behavior: `SET NULL` or `RESTRICT`.

Recommendation: `SET NULL` is acceptable if refresh token cleanup should not remove session history linkage.

### 2. Provider structure foreign keys

#### provider_accounts.created_by_user_id -> users.id

Meaning: a provider account was created by one user.

Recommended delete behavior: `RESTRICT`.

#### provider_members.provider_account_id -> provider_accounts.id

Meaning: a provider membership belongs to one provider account.

Recommended delete behavior: `RESTRICT`.

#### provider_members.user_id -> users.id

Meaning: a provider membership links one global user into one provider account.

Recommended delete behavior: `RESTRICT`.

#### provider_locations.provider_account_id -> provider_accounts.id

Meaning: a location belongs to one provider account.

Recommended delete behavior: `RESTRICT`.

#### provider_staff.provider_account_id -> provider_accounts.id

Meaning: a staff record belongs to one provider account.

Recommended delete behavior: `RESTRICT`.

#### provider_staff.user_id -> users.id

Meaning: a staff member may optionally link to a global user identity.

Recommended delete behavior: `SET NULL` if user linkage is optional and the operational staff record should remain. Otherwise `RESTRICT`.

#### provider_services.provider_account_id -> provider_accounts.id

Meaning: a service belongs to one provider account.

Recommended delete behavior: `RESTRICT`.

#### provider_resources.provider_account_id -> provider_accounts.id

Meaning: a resource belongs to one provider account.

Recommended delete behavior: `RESTRICT`.

### 3. Slot and booking core foreign keys

#### slots.provider_account_id -> provider_accounts.id

Meaning: a slot belongs to one provider account.

Recommended delete behavior: `RESTRICT`.

#### slots.provider_location_id -> provider_locations.id

Meaning: a slot may reference a specific provider location.

Recommended delete behavior: `SET NULL` if location linkage is optional and historical slot retention matters. Otherwise `RESTRICT`.

#### slots.provider_staff_id -> provider_staff.id

Meaning: a slot may reference a specific staff member.

Recommended delete behavior: `SET NULL` if staff linkage is optional and historical slot retention matters. Otherwise `RESTRICT`.

#### slots.provider_service_id -> provider_services.id

Meaning: a slot belongs to one provider service.

Recommended delete behavior: `RESTRICT`.

#### slots.provider_resource_id -> provider_resources.id

Meaning: a slot may reference one optional resource.

Recommended delete behavior: `SET NULL`.

#### slots.created_by_user_id -> users.id

Meaning: a slot was created by one user.

Recommended delete behavior: `RESTRICT`.

#### bookings.slot_id -> slots.id

Meaning: a booking belongs to one slot.

Recommended delete behavior: `RESTRICT`.

#### bookings.provider_account_id -> provider_accounts.id

Meaning: a booking belongs to one provider account.

Recommended delete behavior: `RESTRICT`.

#### bookings.client_user_id -> users.id

Meaning: a booking belongs to one client user.

Recommended delete behavior: `RESTRICT`.

### 4. Payments and refunds foreign keys

#### payments.booking_id -> bookings.id

Meaning: a payment belongs to one booking.

Recommended delete behavior: `RESTRICT`.

#### payments.provider_account_id -> provider_accounts.id

Meaning: a payment belongs to one provider account context.

Recommended delete behavior: `RESTRICT`.

#### refunds.payment_id -> payments.id

Meaning: a refund belongs to one payment.

Recommended delete behavior: `RESTRICT`.

#### refunds.booking_id -> bookings.id

Meaning: a refund also references the booking context directly for operational and reporting clarity.

Recommended delete behavior: `RESTRICT`.

#### refunds.requested_by_user_id -> users.id

Meaning: a refund request may be triggered by one user.

Recommended delete behavior: `SET NULL` or `RESTRICT`.

Recommendation: `SET NULL` can be acceptable if user deletion is ever allowed while financial history must remain intact. If users are effectively never hard-deleted, `RESTRICT` is cleaner.

#### refunds.approved_by_user_id -> users.id

Meaning: a refund may be approved by one user.

Recommended delete behavior: `SET NULL` or `RESTRICT`, using the same logic as `requested_by_user_id`.

### 5. Integration reliability foreign keys

#### webhook_events

No required foreign key in the first version. Webhook events are inbound external records and should usually be stored even if business linkage fails temporarily.

Optional future linkage: a processed webhook may later store related `payment_id` or `booking_id` references if useful.

#### outbox_messages

No required foreign key in the first version. `aggregate_type` plus `aggregate_id` usually provide enough linkage for the skeleton phase.

### 6. Audit and operations foreign keys

#### audit_logs.actor_user_id -> users.id

Meaning: an audit log may record which user performed the action.

Recommended delete behavior: `SET NULL` or `RESTRICT`.

Recommendation: `SET NULL` may be acceptable if user hard deletion is ever possible and audit history must remain. If users are never hard-deleted, `RESTRICT` is preferable.

#### audit_logs entity references

No required foreign key from `entity_id` to business tables in the first version. Audit logs are polymorphic by nature, so `entity_type` plus `entity_id` provide the reference pattern.

### Relationship summary by table

#### users is referenced by

- `user_phones.user_id`
- `user_roles.user_id`
- `refresh_tokens.user_id`
- `user_sessions.user_id`
- `provider_accounts.created_by_user_id`
- `provider_members.user_id`
- `provider_staff.user_id`
- `slots.created_by_user_id`
- `bookings.client_user_id`
- `refunds.requested_by_user_id`
- `refunds.approved_by_user_id`
- `audit_logs.actor_user_id`

#### provider_accounts is referenced by

- `provider_members.provider_account_id`
- `provider_locations.provider_account_id`
- `provider_staff.provider_account_id`
- `provider_services.provider_account_id`
- `provider_resources.provider_account_id`
- `slots.provider_account_id`
- `bookings.provider_account_id`
- `payments.provider_account_id`

#### provider_locations is referenced by

- `slots.provider_location_id`

#### provider_staff is referenced by

- `slots.provider_staff_id`

#### provider_services is referenced by

- `slots.provider_service_id`

#### provider_resources is referenced by

- `slots.provider_resource_id`

#### slots is referenced by

- `bookings.slot_id`

#### bookings is referenced by

- `payments.booking_id`
- `refunds.booking_id`

#### payments is referenced by

- `refunds.payment_id`

#### refresh_tokens is referenced by

- `user_sessions.refresh_token_id`

### Foreign key philosophy notes

#### Historical preservation

Bookings, payments, refunds, webhook events, and audit logs represent business history. These records should not disappear because a parent record was casually deleted.

#### Provider structure changes

If a provider location, staff member, or resource changes over time, historical bookings and slots may still need to remain interpretable. This is one reason snapshot columns in bookings are valuable.

#### Polymorphic records

Audit logs and outbox messages should stay flexible in the foundation stage. They can use `entity_type` and `aggregate_type` patterns rather than trying to enforce every relationship with direct foreign keys.

## Initial constraints and indexes

### Design principle

Foreign keys connect the model. Constraints protect the truth of the model. Indexes make the model usable in real workflows. The most important business rules should be defended by the database, not only by application code.

### General rules

- Constraints should protect important invariants.
- Indexes should be added for real query patterns, not randomly.
- Uniqueness should be enforced where duplicate records would break trust or create confusion.
- PostgreSQL-specific features should be used when they materially improve correctness.
- Financial, booking, and identity records require stricter protection than convenience tables.

### 1. Primary key constraints

All main business tables should have a primary key on `id`.

### 2. Identity and auth constraints

#### users

Recommended constraints:

- Optional unique constraint on `email` if email is used as a trusted identity field
- Check or enum-like validation for `status` if user lifecycle states are defined

#### user_phones

Recommended constraints:

- `unique(phone_number)`
- Only one primary phone per user if multiple phone support exists
- Logical consistency between `is_verified` and `verified_at` where practical

Notes:

- `phone_number` should be normalized before persistence.
- Global phone uniqueness is one of the most important identity rules.

#### otp_challenges

Recommended constraints and indexes:

- `check(attempts_count >= 0)`
- `check(max_attempts > 0)`
- `check(expires_at >= created_at)`
- Index on `phone_number`
- Index on `expires_at`
- Index on `locked_until`

#### user_roles

Recommended constraints:

- `unique(user_id, role_code)`

#### refresh_tokens

Recommended constraints and indexes:

- `unique(token_hash)`
- `check(expires_at > created_at)`
- Index on `user_id`
- Index on `expires_at`
- Index on `revoked_at`

#### user_sessions

Recommended indexes:

- Index on `user_id`
- Index on `refresh_token_id`
- Index on `last_seen_at`
- Index on `revoked_at`

### 3. Provider structure constraints

#### provider_accounts

Recommended constraints and indexes:

- Check that `provider_type` is within allowed values
- Check that `status` is within allowed values
- Index on `created_by_user_id`
- Index on `provider_type`
- Index on `status`

#### provider_members

Recommended constraints and indexes:

- `unique(provider_account_id, user_id)`
- Check that `membership_role` is within allowed values
- Check that `status` is within allowed values
- Index on `user_id`
- Index on `provider_account_id`
- Index on `membership_role`
- Index on `status`

#### provider_locations

Recommended indexes:

- Index on `provider_account_id`
- Index on `status`

Optional recommendation:

- `unique(provider_account_id, name)` if location names must be distinct within one provider

#### provider_staff

Recommended indexes:

- Index on `provider_account_id`
- Index on `user_id`
- Index on `status`

Optional recommendation:

- `unique(provider_account_id, user_id)` if one user should not have duplicate staff records in the same provider

#### provider_services

Recommended constraints and indexes:

- Index on `provider_account_id`
- Index on `status`
- `check(duration_minutes > 0)`
- `check(default_price_amount >= 0)`

Optional recommendation:

- `unique(provider_account_id, name)` if service names must be distinct within one provider

#### provider_resources

Recommended indexes:

- Index on `provider_account_id`
- Index on `status`

Optional recommendation:

- `unique(provider_account_id, name)` if resource names must be distinct within one provider

### 4. Slot constraints and indexes

#### slots

Recommended constraints and indexes:

- `check(end_at > start_at)`
- `check(price_amount >= 0)`
- Check that `status` is within allowed values
- Index on `provider_account_id`
- Index on `provider_location_id`
- Index on `provider_staff_id`
- Index on `provider_service_id`
- Index on `provider_resource_id`
- Index on `start_at`
- Index on `end_at`
- Index on `status`
- Index on `published_at`
- Composite index on `(status, start_at)`
- Composite index on `(provider_account_id, start_at)`

Optional recommendations:

- Composite index on `(provider_account_id, status, start_at)` for provider-side operational listing
- Composite index on `(provider_service_id, start_at)` if service-based browsing is common

Notes:

- Published slot listing will likely be one of the most common reads in the system.
- Time-window filtering must stay fast.

### 5. Booking constraints and indexes

#### bookings

Recommended constraints and indexes:

- Check that `status` is within allowed values
- `check(booked_price_amount >= 0)`
- Index on `slot_id`
- Index on `provider_account_id`
- Index on `client_user_id`
- Index on `status`
- Index on `hold_expires_at`
- Index on `confirmed_at`
- Composite index on `(client_user_id, created_at)`
- Composite index on `(provider_account_id, created_at)`
- Composite index on `(provider_account_id, status)`
- Composite index on `(client_user_id, status)`

Critical business rule:

- Only one active booking may exist for the same slot at a time.

Recommended PostgreSQL solution:

- Use a partial unique index on `slot_id` for active booking states.

Recommended active states for uniqueness protection:

- `hold`
- `payment_pending`
- `confirmed`
- `completed`
- `client_no_show`

Non-blocking states:

- `expired`
- `payment_failed`
- `provider_cancelled`
- `provider_no_show`
- `refunded`

Optional recommendations:

- Composite index on `(slot_id, status)`
- Composite index on `(status, hold_expires_at)` for hold cleanup jobs

### 6. Payment constraints and indexes

#### payments

Recommended constraints and indexes:

- `unique(stripe_payment_intent_id)` where not null
- `unique(stripe_charge_id)` where not null
- `check(amount >= 0)`
- Check that `status` is within allowed values
- Index on `booking_id`
- Index on `provider_account_id`
- Index on `status`
- Index on `payment_provider`
- Index on `succeeded_at`
- Composite index on `(provider_account_id, created_at)`
- Composite index on `(booking_id, status)`

Notes:

- Stripe identifiers should never collide.
- Booking payment lookup must be fast.

### 7. Refund constraints and indexes

#### refunds

Recommended constraints and indexes:

- `unique(stripe_refund_id)` where not null
- `check(amount >= 0)`
- Check that `status` is within allowed values
- Check that `reason_code` is within allowed values
- Index on `payment_id`
- Index on `booking_id`
- Index on `requested_by_user_id`
- Index on `approved_by_user_id`
- Index on `status`
- Index on `reason_code`
- Index on `succeeded_at`

Important rule:

- Refund operations must be idempotent.

Recommendation:

- Enforce uniqueness through either a dedicated idempotency table or a unique business key per refund action.

### 8. Webhook constraints and indexes

#### webhook_events

Recommended constraints and indexes:

- `unique(provider_name, external_event_id)`
- Check that `processing_status` is within allowed values
- Index on `provider_name`
- Index on `event_type`
- Index on `processing_status`
- Index on `received_at`
- Index on `processed_at`

Notes:

- Webhook deduplication is non-negotiable.
- Repeated external delivery must not create repeated business side effects.

### 9. Outbox constraints and indexes

#### outbox_messages

Recommended constraints and indexes:

- `check(attempts_count >= 0)`
- Check that `status` is within allowed values
- Index on `topic`
- Index on `aggregate_type`
- Index on `aggregate_id`
- Index on `status`
- Index on `available_at`
- Index on `processed_at`
- Composite index on `(status, available_at)`

### 10. Audit constraints and indexes

#### audit_logs

Recommended indexes:

- Index on `actor_user_id`
- Index on `entity_type`
- Index on `entity_id`
- Index on `action_code`
- Index on `request_id`
- Index on `created_at`
- Composite index on `(entity_type, entity_id)`
- Composite index on `(actor_user_id, created_at)`

### 11. Recommended status validation strategy

Lifecycle tables should use explicit allowed status values. This can be implemented through database check constraints, PostgreSQL enums, or reference tables.

Recommendation for MVP:

- Use check constraints or PostgreSQL enums for simplicity and strictness.

### 12. Recommended idempotency strategy

Current note:

- Idempotency is required by architecture, but the initial table list does not yet include a dedicated `idempotency_keys` table.

Recommendation:

- Either add `idempotency_keys` as a new foundation table, or enforce idempotency through operation-specific unique identifiers and stored request references.

Preferred approach:

- A dedicated `idempotency_keys` table is cleaner for booking creation, payment initiation, refund execution, and critical admin actions.

Suggested future columns:

- `id`
- `scope_key`
- `idempotency_key`
- `request_hash`
- `response_json`
- `created_at`
- `expires_at`

Suggested uniqueness:

- `unique(scope_key, idempotency_key)`

### 13. Most critical database protections in the MVP

Top priority protections:

- Unique user phone number
- Unique provider membership per provider and user
- Unique webhook external event per provider
- Unique Stripe payment intent and refund references
- One active booking per slot
- Explicit status validation on lifecycle tables

Implementation principle:

If there is any doubt about whether a rule is important enough to enforce at database level, booking, payment, refund, webhook, and identity rules should lean toward stronger enforcement rather than weaker enforcement.

## Entity notes

### Purpose

Entity notes explain the meaning, responsibility, and important implementation notes for each main entity in the MVP backend foundation. The goal is to help the team understand not only how the schema is structured, but why each entity exists.

### Design principle

An entity should have a clear purpose. If a table exists but its responsibility is unclear, implementation will drift.

### 1. users

Meaning: the global identity of a person in the system.

Responsibility: store the core user identity that can participate across multiple roles and contexts.

Important notes:

- A single user may act as a client, a provider-side actor, an admin, or more than one of these at the same time.
- This table should stay generic and not absorb provider-specific structure.
- A user is not the same thing as a provider account.
- A user is not the same thing as a provider staff record, even if one may be linked to the other.
- Avoid turning this table into a dumping ground for unrelated business fields.

### 2. user_phones

Meaning: store phone numbers linked to users and support verified phone identity.

Responsibility: enforce the trusted phone-based registration and verification model.

Important notes:

- Phone numbers should be normalized consistently before persistence.
- Global uniqueness is critical for identity trust.
- Verification status should be clear and auditable.
- Loose handling of phone identity creates fake-user and duplicate-user risk.

### 3. otp_challenges

Meaning: store phone verification attempts and related control data.

Responsibility: support OTP verification flow, including expiration, retry counting, resend timing, and lockout behavior.

Important notes:

- OTP data should be treated as security-sensitive.
- Store OTP hashes rather than plain codes where possible.
- Expired and failed OTP records are still operationally useful for auditing and security analysis.
- This table supports trust and abuse protection, not only UX.

### 4. user_roles

Meaning: store global roles attached to a user.

Responsibility: provide global role context such as client, admin, or super-admin.

Important notes:

- This is not enough by itself for provider-side authorization.
- Provider-side access must also depend on provider membership and scope.
- Do not confuse global role with resource ownership.

### 5. refresh_tokens

Meaning: store revocable token continuation records.

Responsibility: support JWT session continuation, refresh rotation, and revocation.

Important notes:

- Store token hashes, not raw tokens.
- This table is part of security posture, not just session convenience.
- Revocation handling should be explicit and testable.

### 6. user_sessions

Meaning: store session or device-level traces of authenticated usage.

Responsibility: support session visibility, revocation context, and security or audit understanding.

Important notes:

- This table is useful even if the MVP does not expose session management UI.
- Session history improves debugging and trust.
- It should not become the primary source of auth truth, but it is valuable supporting context.

### 7. provider_accounts

Meaning: represent the provider-side business entity.

Responsibility: model either an individual provider or an organization provider through one shared structure.

Important notes:

- This is the main provider identity in the business domain.
- It should not be replaced with raw user identity.
- It supports both solo providers and larger organizations without separate architectures.
- It is a core ownership boundary in the system.

### 8. provider_members

Meaning: link global users into provider accounts.

Responsibility: define who belongs to a provider account and in what provider-side role.

Important notes:

- This is one of the most important authorization tables in the whole backend.
- Being a provider-side actor should depend on membership, not assumptions.
- This table should be central in authorization policy checks.
- It expresses provider ownership scope better than global roles alone.

### 9. provider_locations

Meaning: store where services may be delivered.

Responsibility: model physical or operational locations under a provider account.

Important notes:

- The location model should stay practical and avoid overcomplicated address structure too early.
- Historical bookings may still refer to locations that later become inactive.
- Location naming and status should be managed consistently.

### 10. provider_staff

Meaning: store provider-associated personnel.

Responsibility: support assigning staff to slots and provider operations.

Important notes:

- Some staff may later be linked to full global user accounts.
- It is acceptable in the MVP foundation to leave room for both identity-linked and operational-only staff records.
- Do not assume staff and user are always identical concepts.

### 11. provider_services

Meaning: store the services that a provider offers to clients.

Responsibility: define what is actually being sold and booked.

Important notes:

- A service is a catalog or business concept, not a booking record.
- Service configuration may change over time, so bookings should preserve snapshots of what was purchased when needed.
- Duration and default pricing belong naturally here, even though slot-level overrides may exist later.

### 12. provider_resources

Meaning: store optional allocatable resources needed for service execution.

Responsibility: support future-safe modeling of limited operational assets such as rooms, chairs, stations, or equipment.

Important notes:

- This table may be lightly used in the MVP, but including it in the foundation avoids future structural hacks.
- Resources should remain optional unless business rules require them.

### 13. slots

Meaning: store provider-published offerable time windows.

Responsibility: represent the sellable availability unit that a client can browse and attempt to book.

Important notes:

- A slot is not itself a booking.
- A slot should not become a dumping place for payment or refund state.
- A slot expresses supply.
- Booking expresses reservation truth.
- Keep this distinction clear in code and schema.
- Historical interpretation of slots may matter even after provider-side changes.

### 14. bookings

Meaning: store reservation attempts and booking lifecycle state.

Responsibility: act as the central aggregate for reservation truth. It connects slot occupancy, client action, payment flow, and business outcome.

Important notes:

- This is one of the most important entities in the system.
- The booking lifecycle must be explicit and state-driven.
- The hold model is critical for concurrency safety.
- Booking should preserve snapshots of purchased business context where needed.
- Booking should be treated with stronger integrity discipline than most other entities.

### 15. payments

Meaning: store payment lifecycle records for bookings.

Responsibility: capture integration-level payment truth, processor references, and payment state progression.

Important notes:

- The frontend must not be treated as the final source of payment truth.
- Stripe-confirmed outcomes must drive state transitions.
- The model should remain ready for both platform-collected and connected-account-collected payment structures.
- External processor references must be persisted explicitly.

### 16. refunds

Meaning: store refund lifecycle records.

Responsibility: record why money was reversed, how much was reversed, and what the processor returned.

Important notes:

- Refunds are not just payment flags.
- They deserve their own entity because they carry their own lifecycle, reason, and audit needs.
- Refund handling must be idempotent and traceable.
- Provider cancellation and provider no-show are important refund triggers in the MVP.

### 17. webhook_events

Meaning: store inbound external event deliveries.

Responsibility: provide deduplication, processing safety, and traceability for Stripe webhook handling.

Important notes:

- External systems may retry events.
- This entity is part of reliability design, not just logging.
- Store inbound event identity even when processing fails.
- This table is one of the backend's defenses against duplicate business side effects.

### 18. outbox_messages

Meaning: store deferred side effects or messages for asynchronous processing.

Responsibility: support async handling such as emails and lay groundwork for reliable post-transaction processing.

Important notes:

- This is currently a skeleton or foundation entity, but it is strategically important.
- It helps separate transaction-safe business changes from side effects.
- It should remain generic enough to support growth without becoming vague.

### 19. audit_logs

Meaning: store traceable action history.

Responsibility: record who did what, to which entity, and when, with enough context to support operational trust and debugging.

Important notes:

- Audit logs are not decorative.
- They are part of platform trust, especially for auth, booking, payment, refund, and admin actions.
- Polymorphic entity references are normal here.
- Avoid overcoupling this entity to every domain table through forced foreign keys.

### Entity interpretation principles

#### Identity vs provider distinction

A user is a global identity. A provider account is a business-side ownership container. A staff record is an operational role or person context. These are related but not interchangeable.

#### Slot vs booking distinction

A slot expresses provider supply. A booking expresses reservation and occupancy truth. This distinction must remain clear across schema, code, and API design.

#### Payment vs refund distinction

A refund is not only a payment status decoration. It is its own financial event with its own reason and traceability.

#### Security-sensitive entities

The following entities should be treated with heightened care:

- `user_phones`
- `otp_challenges`
- `refresh_tokens`
- `user_sessions`
- `payments`
- `refunds`
- `webhook_events`

#### Most critical entities for MVP correctness

- `user_phones`
- `provider_members`
- `slots`
- `bookings`
- `payments`
- `refunds`
- `webhook_events`
- `audit_logs`

## Initial ERD notes

### Design principle

The ERD should reflect domain truth, not only table existence. It should make ownership, lifecycle flow, and system-critical relationships obvious. Clarity matters more than visual completeness.

### ERD drawing goal

The initial ERD should make these things immediately visible:

- One global user identity model
- One provider ownership model
- One slot supply model
- One booking occupancy model
- One payment and refund financial model
- One reliability and audit support layer

### Main entity clusters

#### 1. Identity and auth cluster

Contains:

- `users`
- `user_phones`
- `otp_challenges`
- `user_roles`
- `refresh_tokens`
- `user_sessions`

Meaning: trusted identity, phone verification, login continuation, and session traceability.

How it should look:

- `users` is the central node of this cluster.
- `user_phones`, `user_roles`, `refresh_tokens`, and `user_sessions` visibly depend on `users`.
- `otp_challenges` may sit near `user_phones`, but it is more flow-oriented than strictly identity-owned.

Important note: the identity cluster is security-critical, but it is not the provider-ownership root of the business domain.

#### 2. Provider structure cluster

Contains:

- `provider_accounts`
- `provider_members`
- `provider_locations`
- `provider_staff`
- `provider_services`
- `provider_resources`

Meaning: provider-side business container and operational structure.

How it should look:

- `provider_accounts` is the central node.
- `provider_members` connects `provider_accounts` to `users`.
- Locations, staff, services, and resources appear as children of `provider_accounts`.

Important note: this is one of the most important ownership clusters in the whole backend and is central for authorization logic.

#### 3. Supply and booking cluster

Contains:

- `slots`
- `bookings`

Meaning: supply and reservation truth.

How it should look:

- `slots` should sit between provider structure and bookings.
- `slots` should point back to `provider_accounts`, `provider_locations`, `provider_staff`, `provider_services`, and optionally `provider_resources`.
- `bookings` should point to `slots`, `provider_accounts`, and `users` through `client_user_id`.

Important note: the slot-vs-booking distinction should be visually obvious.

#### 4. Financial cluster

Contains:

- `payments`
- `refunds`

Meaning: financial truth for the booking lifecycle.

How it should look:

- `payments` should depend on `bookings`.
- `payments` should also reference `provider_accounts`.
- `refunds` should depend on `payments` and also reference `bookings`.
- `refunds` may reference `users` through `requested_by_user_id` and `approved_by_user_id`.

Important note: payments and refunds should look downstream from bookings, not parallel to them.

#### 5. Reliability and audit cluster

Contains:

- `webhook_events`
- `outbox_messages`
- `audit_logs`

Meaning: reliability, async processing, traceability, and safe integration handling.

How it should look:

- `webhook_events` can sit near payments because Stripe processing often affects payment state.
- `outbox_messages` can sit near the center-right or bottom as an infrastructure support entity.
- `audit_logs` can sit separately as a cross-cutting traceability entity.

Important note: these tables are support entities, but some are still mission-critical.

### Most important entity roots

- `users` is the root of global identity.
- `provider_accounts` is the root of provider-side ownership.
- `slots` is the root of supply within the booking flow.
- `bookings` is the central aggregate of reservation truth.
- `payments` is the central aggregate of payment truth for a booking.

### Which entities are most central in the ERD

Top central entities:

- `users`
- `provider_accounts`
- `slots`
- `bookings`
- `payments`

Why these are central:

- `users` anchors identity.
- `provider_accounts` anchors provider ownership.
- `slots` anchors supply.
- `bookings` anchors reservation lifecycle.
- `payments` anchors financial outcome.

### Most important relationships to show clearly

#### Identity relationships

- `users -> user_phones`
- `users -> user_roles`
- `users -> refresh_tokens`
- `users -> user_sessions`

#### Provider relationships

- `provider_accounts -> provider_members`
- `provider_accounts -> provider_locations`
- `provider_accounts -> provider_staff`
- `provider_accounts -> provider_services`
- `provider_accounts -> provider_resources`
- `users -> provider_members`

#### Supply relationships

- `provider_accounts -> slots`
- `provider_locations -> slots`
- `provider_staff -> slots`
- `provider_services -> slots`
- `provider_resources -> slots`

#### Booking relationships

- `slots -> bookings`
- `provider_accounts -> bookings`
- `users -> bookings` through `client_user_id`

#### Financial relationships

- `bookings -> payments`
- `provider_accounts -> payments`
- `payments -> refunds`
- `bookings -> refunds`

#### Audit and support relationships

- `users -> audit_logs` through `actor_user_id`
- `refresh_tokens -> user_sessions`
- `users -> provider_accounts` through `created_by_user_id`
- `users -> slots` through `created_by_user_id`

### Cardinality notes for ERD thinking

- `users -> user_phones`: one-to-many
- `users -> user_roles`: one-to-many
- `users -> refresh_tokens`: one-to-many
- `users -> user_sessions`: one-to-many
- `provider_accounts -> provider_members`: one-to-many
- `users -> provider_members`: one-to-many across different providers, but usually at most one membership per provider per user
- `provider_accounts -> provider_locations`: one-to-many
- `provider_accounts -> provider_staff`: one-to-many
- `provider_accounts -> provider_services`: one-to-many
- `provider_accounts -> provider_resources`: one-to-many
- `provider_accounts -> slots`: one-to-many
- `slots -> bookings`: one-to-many historically, but only one active booking at a time
- `users -> bookings`: one-to-many from the client perspective
- `bookings -> payments`: usually one-to-many if retries or multiple payment attempts are allowed conceptually
- `payments -> refunds`: one-to-many

### Where integrity pressure is highest

High integrity pressure relationships:

- `user_phones -> users`
- `provider_members -> provider_accounts` and `users`
- `slots -> provider_accounts` and `provider_services`
- `bookings -> slots`
- `payments -> bookings`
- `refunds -> payments`
- `webhook_events` uniqueness by provider and external event id

### Suggested ERD layout

- Top-left: identity and auth cluster around `users`
- Top-right: provider structure cluster around `provider_accounts`
- Center: `slots` and `bookings`
- Lower-center or lower-right: `payments` and `refunds`
- Far-right or bottom: `webhook_events`, `outbox_messages`, `audit_logs`

### What the first ERD should not try to do

- Do not show every index.
- Do not overload the diagram with every nullable detail.
- Do not turn the first ERD into a physical database implementation sheet.
- Do not over-emphasize support tables compared to the business core.
- Do not hide the slot-vs-booking distinction.

### Recommended ERD emphasis order

#### First emphasis

- `users`
- `provider_accounts`
- `slots`
- `bookings`
- `payments`
- `refunds`

#### Second emphasis

- `provider_members`
- `provider_locations`
- `provider_staff`
- `provider_services`
- `provider_resources`

#### Third emphasis

- `user_phones`
- `user_roles`
- `refresh_tokens`
- `user_sessions`
- `webhook_events`
- `outbox_messages`
- `audit_logs`

### Interpretation notes

#### Identity is global

The same user may appear in multiple system contexts. Do not draw separate client and provider user roots.

#### Provider scope is explicit

Provider access should visually pass through `provider_members` rather than vague assumptions about user type.

#### Booking is the operational center

If one entity feels most like the center of business workflow, it should be `bookings`. That is where supply, client action, payment progression, and outcome meet.

#### Payments are downstream, not independent

Payment should appear as a consequence-linked financial entity tied to booking. It is critical, but it should not visually replace booking as the business center.

#### Support entities are important but secondary

Webhook, outbox, and audit entities protect reliability and traceability. They should appear clearly, but the ERD should still communicate business core first.
