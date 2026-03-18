# Technical architecture

### System architecture overview

The platform follows a standard client–server architecture.

The frontend application communicates with a backend API layer.

The backend manages business logic, slot lifecycle, payment integration, and state transitions.

Stripe is used for payment processing and payout management.

A relational database stores users, providers, slots, bookings, payments, and metrics.

The system must ensure atomic booking operations to prevent double booking.

All state transitions must be logged and auditable.

The architecture must support future expansion into bidding mechanics and dispute workflows.

### Core modules

The system is divided into the following logical modules: Authentication Module - Handles user login, phone verification, and session management.

Provider Module - Manages provider profiles, onboarding, and subscription tiers.

Slot Module - Handles slot creation, publishing, lifecycle transitions, and visibility.

Booking Module - Handles atomic reservation, payment initiation, and confirmation.

Payment Module - Integrates with Stripe for payment capture, refunds, and payouts.

Payout Module - Manages delayed payouts after appointment time passes.

Cancellation Module - Handles provider cancellation logic and rating updates.

Admin Module - Provides moderation and oversight tools.

Notification Module - Sends transactional emails and reminders.

Each module must have clear boundaries to support future feature expansion.

### Database architecture (high-level entities)

Core entities include: Users - Account data, phone verification status, Stripe customer ID.

Providers Business profile, subscription tier, reliability metrics.

Slots - Time-based availability units, status field, provider reference.

Bookings - Reference to user and slot, payment status, timestamps.

Payments - Stripe payment intent references, refund status.

Payouts - Provider payout records, payout status, payout date.

ProviderMetrics - Cancellation rate, total bookings, reliability score.

Subscriptions - Tier type, billing status, renewal date.

All tables must include audit timestamps.

All foreign key relationships must be enforced at database level.

### Booking concurrency strategy

Booking must be atomic.

When a user initiates checkout, the system must temporarily lock the slot.

The slot enters a short-lived reserved state during payment processing.

Stripe idempotency keys must be used to prevent duplicate payment creation.

If payment succeeds, the slot transitions to booked.

If payment fails or times out, the slot returns to public.

The reserved state is not a grace period for users; it is purely transactional.

Clear feedback must be given to users if the slot becomes unavailable during checkout.

### Stripe integration architecture

Each user has a Stripe customer object.

Each booking creates a Stripe payment intent.

Payment is captured immediately upon confirmation.

Refunds are triggered automatically when provider cancellation occurs.

Stripe Connect should be used for provider payout management.

Funds are held until appointment completion logic triggers payout.

Payout logic must verify appointment time has passed and review window expired.

Stripe webhooks must be implemented to handle asynchronous events.

All Stripe event handling must be idempotent and logged.

### Payout timing logic

After appointment end time passes, booking enters payout-eligible state.

An optional review window may delay payout to allow user feedback.

A scheduled job checks for payout-eligible bookings.

Payout job triggers Stripe transfer to provider account.

Payout status must be tracked in database.

Failures must trigger retry logic and alerting.

### Reliability and cancelation metrics

Each provider has a cancellation counter.

Cancellation rate is calculated as cancelled bookings divided by total bookings.

Reliability score may be derived from cancellation rate and booking volume.

Metrics must update automatically on cancellation events.

Metrics must be queryable for admin dashboard and possible public display.

Architecture must allow future introduction of financial penalties tied to metrics.

### Notification infrastructure

Transactional notifications include: Booking confirmation to user.

Booking confirmation to provider.

Refund notification.

Reminder before appointment time.

Notification system may use email initially.

Push notifications may be introduced later.

All notifications must be logged.

### Security and compliance

User authentication must use secure password hashing.

Phone verification must prevent fake account creation.

Card details must never be stored directly; only Stripe tokens are stored.

All payment-related endpoints must require strong authentication.

Audit logging must exist for financial events.

Compliance with EU consumer protection and data protection regulations must be considered.

### Extensibility planning

Slot state machine must allow insertion of bidding state in future.

Payment module must allow introduction of penalties and partial refunds.

Database schema must avoid rigid coupling to current verticals.

Location structure must allow expansion to new cities.

Architecture must allow scaling to additional categories beyond hair and restaurants.

Dispute system must be addable without redesigning booking logic.
