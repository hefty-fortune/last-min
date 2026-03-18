# Product specifications

### MVP functional scope

The MVP allows curated providers to manually publish last-minute availability slots.

Users can browse public slots and immediately confirm and pay to secure a booking.

> Note: Makes sense for fixed price services (I.e. tickets), but variable cost services might require

minimum reservation fee. Reservation fee should probably be symbolic and we consider them loss leaders.

Bookings are confirmed automatically upon successful payment.

The booking system operates on strict first-come, first-serve logic.

Users cannot cancel for refund.

Providers can cancel, which triggers automatic user refund and increases provider cancellation metrics.

> Note: Cancelation window should be visible to user in order to avoid confusion and inconvenience

Stripe is used for payment capture and payout handling.

The MVP focuses exclusively on Zagreb and two verticals: high-end hair salons and restaurants.

All additional features such as bidding, external system integration, and financial penalties are out of MVP scope.

### Core booking flow

Provider creates slot and publishes it.

Slot becomes publicly visible and bookable.

User selects slot and initiates booking.

System performs atomic reservation control during checkout to prevent double booking.

User completes Stripe payment.

Booking is automatically confirmed.

User receives confirmation notification.

Provider receives booking notification.

After appointment time passes and review window expires, payout is triggered.

If provider cancels before appointment, user receives automatic refund.

### Slot lifecycle and state machine

Slot states include: Draft – created but not visible.

Public – visible and bookable.

Reserved – temporary transactional state during payment process.

Booked – successfully paid and confirmed.

Completed – appointment time passed and eligible for payout.

Cancelled – provider cancelled before service; refund triggered.

Expired – appointment time passed without booking.

All state transitions must be logged and auditable.

Concurrency control must ensure only one successful booking per slot.

### User features (MVP)

User registration and authentication.

> Note: Might be a good idea to have SSO system, I.e. google so users can easily access app

Mandatory phone verification.

Secure payment method storage via Stripe.

Browse slots with filtering by: • Category • Time window • Location area • Price range View slot details including provider information, time, duration, and price.

Immediate booking and payment flow.

Booking confirmation screen.

Booking history view.

Optional review submission after appointment.

Basic account management.

### Provider features

Provider onboarding and approval process.

Provider profile management.

Manual slot creation including: • Time • Duration • Price • Capacity • Notes Publish and unpublish slot (if not booked).

View upcoming bookings.

Cancel booking (triggers refund and rating update).

Subscription management interface.

Basic booking history view.

### Admin capabilities

Approve or reject provider applications.

View provider cancellation metrics.

View booking records.

Basic moderation of providers and slots.

Manual override ability for exceptional cases.

### Cancellation rules

User cancellation results in no refund.

Provider cancellation results in automatic refund to the user.

Provider cancellation increases visible cancellation metrics.

Cancellation metrics may be displayed publicly as reliability indicator.

Financial penalties for providers are not included in MVP but architecture must allow extension.

Dispute resolution remains minimal in MVP and will be expanded in later phases.

### Subscription tiers

Subscription tiers provide visibility and operational advantages.

Commission percentage remains unchanged across tiers.

Tier benefits may include: • Priority listing • Featured exposure • Advanced analytics • Custom branding options • Increased publishing limits • Faster payout cycles Subscription billing and management are handled within provider portal.

### Concurrency and fairness requirements

The system must prevent double booking of a slot.

Atomic reservation logic must be implemented during payment initiation.

Stripe idempotency keys must be used for payment safety.

If two users attempt to book simultaneously, only one transaction may complete successfully.

The other user must receive clear notification that the slot is no longer available.

Speed and clarity are critical for user trust.

### Notification and communication

User receives booking confirmation notification.

Provider receives booking confirmation notification.

User receives refund notification if provider cancels.

Reminder notifications may be sent before appointment time.

Communication must be minimal and professional.

All transactional notifications must be reliable and logged.

### Extensibility architecture notes

The product architecture must support future bidding mechanics.

The architecture must support future financial penalties for providers.

The architecture must allow integration with external booking systems.

The architecture must allow advanced dispute workflows.

The architecture must support expansion to new verticals and cities.

State machine and payment model must be designed with these extensions in mind.
