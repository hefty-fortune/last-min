# Overview and direction

## Foundation overview

The backend foundation is the first stable technical base for the MVP. The goal is not to build every feature immediately, but to establish the correct domain model, state transitions, data integrity rules, and module boundaries first.

The system is a modular monolith in plain PHP, using dependency injection and layered modules. PostgreSQL is the primary database. Authentication is handled by the platform's own verified phone-based registration and JWT-based sessions.

The MVP supports fixed-price last-minute slots. A provider publishes a slot, a client books it, and payment is completed immediately. Provider cancellation and provider no-show trigger refunds. Client cancellation is not allowed in the MVP. Client no-show is treated as a consumed service and does not trigger a refund.

Auditability is required from the beginning. The architecture should be clean, understandable, and easy to continue building on safely.

## Backend principles

The following principles guide backend decisions:

- Correctness is preferred over short-term implementation speed.
- Core booking and payment consistency matters more than controller simplicity.
- Business rules belong in the domain and application layers, not inside controllers.
- Controllers should orchestrate requests and responses, not contain core workflow logic.
- Availability must not be duplicated inconsistently across slots, bookings, and payments.
- Each module should have a clear responsibility and should not absorb unrelated logic.
- State transitions must be explicit and controlled.
- Important operations must be idempotent.
- Side effects such as emails should be asynchronous where possible.
- Every critical change must be traceable through audit logs.
- The codebase should be readable and teachable to all developers.

## MVP scope

### Included in the MVP

- Phone-based registration and verification
- Provider creation and management
- Locations, staff, services, resources, and slot publishing
- Client browsing and booking of published slots
- Immediate payment using Stripe
- Provider cancellation refund logic
- Provider no-show refund logic
- Admin visibility and operational override capabilities
- Email notifications

### Excluded from the MVP

- Auctions and bidding
- Dynamic pricing
- Coupons and loyalty systems
- Push notifications and in-app notifications
- Reviews and ratings
- Advanced payout and accounting engines
- Multi-provider payment abstraction beyond a foundation-ready design
- Advanced recommendation or scheduling intelligence
