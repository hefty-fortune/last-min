# API and integration

## API style decision

The system uses REST as the primary application API style.

- Stripe webhooks are used for asynchronous payment result delivery.
- Async jobs are used for side effects such as email and deferred processing.
- The architecture may evolve later, but the MVP foundation should avoid introducing unnecessary early complexity such as a premature external event architecture.

## Initial API surface

The first important endpoint families are:

- Auth endpoints for registration, verification, login, refresh, logout, and current user retrieval
- Provider endpoints for provider account creation and configuration
- Slot endpoints for creation, publishing, editing, listing, and cancellation
- Booking endpoints for booking creation and booking inspection
- Provider and admin endpoints for completion, no-show, and cancellation actions
- Payment webhook endpoints for Stripe callbacks
- Admin endpoints for booking review, refunds, and audit inspection

Suggested endpoint groups:

- `/auth`
- `/me`
- `/providers`
- `/slots`
- `/bookings`
- `/payments/webhooks/stripe`
- `/admin`

## Stripe integration notes

Stripe is the payment provider in the MVP.

- Payment flow should be modeled around persisted payment intent references.
- The system must keep room for both platform-held and connected-account models.
- Payment confirmation should be driven by server-side logic and Stripe webhook confirmation.
- Refund references from Stripe must be stored.
- Stripe-specific details should live in infrastructure and integration layers, not leak across the whole application.

## Async jobs and outbox

The asynchronous processing strategy should include the following rules:

- Email sending should be asynchronous.
- Hold expiry processing should be asynchronous.
- Side effects should not be tightly coupled to synchronous request completion when avoidable.
- The backend foundation should include at least an outbox skeleton.
- The outbox skeleton leaves room for more reliable domain event delivery later.

The MVP does not need a fully mature event-driven architecture, but it should not block that future path.
