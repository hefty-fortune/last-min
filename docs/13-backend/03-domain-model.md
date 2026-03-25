# Domain model

## Main domain modules

The backend is organized around the following bounded contexts or modules:

- Identity
- Auth
- Users
- Providers
- Locations
- Staff
- Services
- Resources
- Slots
- Bookings
- Payments
- Refunds
- Notifications
- Admin
- Audit

### Module responsibility notes

#### Identity

Identity owns the trusted user identity layer. It handles phone-based registration, phone verification, OTP lifecycle, and uniqueness of verified identities. It should not contain provider business logic or booking workflows.

#### Auth

Auth owns access control and session trust. It handles JWT issuance, refresh tokens, login and logout flow, and session or device tracking. It should not own provider membership logic or booking state transitions.

#### Users

Users owns the global user profile model. It represents a single user identity that may hold multiple roles across the system. It should not duplicate provider-specific or admin-specific structure.

#### Providers

Providers owns provider-side business identity. A provider may be an individual or an organization. This module is responsible for provider accounts, provider membership, and provider ownership boundaries. It should not own booking or payment logic.

#### Locations

Locations owns physical or operational places where services are delivered. It should support one or more locations per provider account.

#### Staff

Staff owns provider-associated personnel who may perform services or be attached to slots. It should support both simple and more structured provider teams.

#### Services

Services owns the definition of what the provider offers to clients. A service is the logical unit being booked.

#### Resources

Resources owns optional allocatable assets, such as a chair, room, table, or other limited resource needed for a slot. Resources are optional in the MVP, but the model should support them.

#### Slots

Slots owns provider-published availability offers. A slot represents a sellable, time-bound service opportunity with a fixed price in the MVP. Slots should not own payment state or refund state.

#### Bookings

Bookings owns the reservation lifecycle. It is the central aggregate for client reservation attempts and booking outcome state. It must coordinate safely with slots, payments, and refunds. This is one of the most critical modules in the system.

#### Payments

Payments owns payment lifecycle and Stripe payment references. It tracks payment status and integration-level payment truth. It should not decide booking business policy on its own.

#### Refunds

Refunds owns refund records and refund lifecycle. It tracks why a refund happened, for how much, and what the processor returned.

#### Notifications

Notifications owns outbound communication such as confirmation emails, refund emails, and operational emails. It should be designed so that side effects can happen asynchronously.

#### Admin

Admin owns internal operational actions and internal oversight capabilities. It supports staff operations such as viewing bookings, triggering manual actions, and resolving exceptional cases. It should not bypass domain rules informally.

#### Audit

Audit owns traceability. It records who did what, when, to which entity, and with what before and after context where appropriate. Audit must exist from the beginning for all critical actions.

## Boundary principle

Each module should have a clear responsibility. Cross-module workflows are allowed, but module ownership of business truth must remain clear. Controllers should orchestrate modules, not replace them.

## User and role model

The system uses one global user identity.

- A single user can have multiple roles.
- Roles may include `client`, `provider member`, `admin`, and `super-admin`.
- A user may be both a client and a provider-side actor.
- Role assignment should not require duplicate user creation.
- Authentication must be independent from provider account structure.
- Provider access should be granted through membership relationships, not by duplicating user records.
- The phone number must be globally unique at the identity level.
- Verification and session trust belong to the identity and auth layer.

## Provider model

The provider-side model follows these rules:

- A provider account can represent either an individual or an organization.
- A provider account has a `provider_type` field.
- `provider_type` can be `individual` or `organization`.
- A provider account can have one or more locations.
- A provider account can have one or more staff members.
- A provider account can have one or more services.
- A provider account can have optional resources.
- Provider members connect users to provider accounts with role and permission context.
- The model should support small individual providers without needing a separate architecture.
- The same model should scale to organizations with multiple staff and locations.

## Slot model

A slot is a provider-published offering of a specific time window for a service.

- A slot belongs to one provider account.
- A slot may belong to one location.
- A slot may belong to one staff member.
- A slot belongs to one service.
- A slot may optionally reference a resource.
- A slot has a start time and end time.
- A slot has a fixed price and currency in the MVP.
- A slot should not carry payment lifecycle logic.
- A slot should not directly represent whether money was refunded.
- Slot availability should be derived through valid booking logic, not duplicated informally.

Recommended slot statuses:

- `draft`
- `public`
- `reserved`
- `booked`
- `completed`
- `cancelled`
- `expired`

## Booking model

A booking represents a client's reservation attempt and lifecycle.

- A booking belongs to one client user.
- A booking belongs to one slot.
- A booking belongs to one provider account.
- A booking may contain pricing snapshots relevant at booking time.
- A booking is the central place where reservation state is tracked.
- Payment records attach to bookings.
- Refund records attach to bookings through payments.
- Booking records must be auditable.
- Booking records must support operational review by admin.

Recommended booking statuses:

- `hold`
- `payment_pending`
- `confirmed`
- `completed`
- `expired`
- `payment_failed`
- `provider_cancelled`
- `provider_no_show`
- `client_no_show`
- `refunded`

## Payment and refund model

At foundation level, the financial domain follows these rules:

- Payment handling uses Stripe in the MVP.
- The exact long-term money model is not yet finalized.
- The backend foundation must remain ready for both platform-collected and connected-account-collected payment models.
- Payments should be stored independently from bookings but linked to them.
- Refunds should be stored independently from payments but linked to both payment and booking context.
- Stripe references must be persisted for tracing and reconciliation.
- Refund actions must be idempotent and auditable.

Recommended payment statuses:

- `requires_action`
- `processing`
- `succeeded`
- `failed`
- `cancelled`
- `refunded`
- `partially_refunded`

Recommended refund reasons:

- `provider_cancelled`
- `provider_no_show`
- `admin_manual`
- `payment_error`
- `other`
