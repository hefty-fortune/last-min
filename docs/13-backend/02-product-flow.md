# Product flow

## Core user flow

The main business flow is:

1. A provider creates a provider account as either an individual or an organization.
2. A provider configures locations, staff, services, and optional resources.
3. A provider publishes a last-minute slot with a fixed price.
4. A client registers and verifies identity using phone OTP.
5. A client browses available published slots.
6. A client chooses a slot and attempts to book it.
7. The system creates a temporary booking hold.
8. The system initiates payment through Stripe.
9. After successful payment confirmation, the booking becomes confirmed.
10. The provider performs the service.
11. If the service is completed normally, the booking becomes completed.
12. If the provider cancels or does not show up, the system triggers refund logic.
13. If the client does not show up, the booking is treated as consumed and no refund is issued.

## Booking flow with hold model

Booking must use a hold-based model rather than a naive "payment first, booking later" approach.

Two clients can attempt to book the same slot nearly simultaneously. To prevent double booking and payment confusion, booking begins with a hold. A hold temporarily reserves the slot for a limited time window. During that period, another active booking for the same slot cannot be created.

The payment flow happens while the booking is in `hold` or `payment_pending`. If payment succeeds, the booking becomes `confirmed`. If payment fails or the hold expires, the booking becomes `payment_failed` or `expired`. The slot becomes available again when no active booking is still holding it.

Recommended rule:

- Hold duration should be explicitly configurable.
- A reasonable initial default is 5 minutes.

## Cancellation and no-show rules

The backend must reflect the following business rules consistently across booking status, payment status, refund records, and audit logs:

- Client cancellation is not supported in the MVP.
- If a client does not show up, the booking is treated as a consumed service opportunity.
- Client no-show does not trigger a refund.
- Provider cancellation triggers a refund.
- Provider no-show triggers a refund.
- Admin must be able to review and override exceptional cases where necessary.
