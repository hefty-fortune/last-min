# State machines

## Booking state machine

### Booking states

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

### State definitions

#### hold

A temporary reservation was created for the slot. The slot is protected while the user completes payment. The hold must have an expiration timestamp.

#### payment_pending

The system has initiated payment and is waiting for the final outcome. This may overlap conceptually with `hold`, but it remains explicit for operational clarity.

#### confirmed

Payment succeeded and the booking is valid. The slot is now fully booked.

#### completed

The service happened successfully and is considered fulfilled.

#### expired

The booking hold expired before payment completed successfully.

#### payment_failed

Payment did not succeed and the booking did not become confirmed.

#### provider_cancelled

The provider cancelled a confirmed booking.

#### provider_no_show

The provider failed to deliver the service at the booked time.

#### client_no_show

The client failed to appear. In the MVP this is treated as a consumed service opportunity and does not trigger refund.

#### refunded

A refund was completed and the booking is marked as financially reversed if the product chooses to represent refund as a booking-level terminal state.

### Allowed transitions

- `hold -> payment_pending` when payment is initiated successfully after booking creation
- `hold -> expired` when hold duration expires before successful payment
- `payment_pending -> confirmed` when Stripe success is verified through backend-controlled payment confirmation
- `payment_pending -> payment_failed` when payment fails or is cancelled
- `confirmed -> completed` when the service is delivered successfully
- `confirmed -> provider_cancelled` when the provider cancels after confirmation
- `confirmed -> provider_no_show` when the provider does not show up
- `confirmed -> client_no_show` when the client does not show up
- `provider_cancelled -> refunded` if refund is represented as a booking-level terminal state
- `provider_no_show -> refunded` if refund is represented as a booking-level terminal state

### Important notes

- A booking should be created before final payment completion by using a hold model.
- A booking must not jump directly from `hold` to `completed`.
- A booking must not become `confirmed` without verified successful payment.
- A booking in `expired` or `payment_failed` must stop blocking future booking attempts.
- Refund may be represented either as a booking terminal state or only in payment and refund records. The implementation must choose one model and apply it consistently.

### Invalid transition principle

Any transition not explicitly allowed by this document is invalid by default.

### MVP operational interpretation

- Client cancellation is not supported.
- Client no-show does not refund the client.
- Provider cancellation triggers refund flow.
- Provider no-show triggers refund flow.

## Payment state machine

### Payment states

- `requires_action`
- `processing`
- `succeeded`
- `failed`
- `cancelled`
- `refunded`
- `partially_refunded`

### State definitions

#### requires_action

The payment exists but needs additional completion steps depending on the payment flow.

#### processing

The payment is being processed and final success or failure is not yet confirmed.

#### succeeded

The payment was successfully completed.

#### failed

The payment did not complete successfully.

#### cancelled

The payment attempt was cancelled and is no longer active.

#### refunded

The payment was fully refunded.

#### partially_refunded

Only part of the original payment amount was refunded.

### Allowed transitions

- `requires_action -> processing`
- `requires_action -> failed`
- `requires_action -> cancelled`
- `processing -> succeeded`
- `processing -> failed`
- `processing -> cancelled`
- `succeeded -> refunded`
- `succeeded -> partially_refunded`
- `partially_refunded -> refunded`

### Important notes

- The frontend must not be treated as the source of truth for final payment status.
- Stripe webhooks and backend verification must drive final payment transitions.
- The payment record must store external Stripe references for traceability.
- Payment state transitions must be auditable.
- Refund operations must be idempotent.

### MVP interpretation

- Payment happens immediately during booking flow.
- Booking becomes confirmed only after payment is verified as successful.
- Provider cancellation and provider no-show may lead to refund transitions.

## Refund state machine

The refund lifecycle should support at least the following behavior:

- A refund request may begin in `pending` or `requested` if the team wants intermediate review.
- A direct automated refund may begin immediately in `processing`.
- A refund may transition to `succeeded`.
- A refund may transition to `failed`.
- Manual or admin refund actions must be auditable.
- Duplicate refund attempts for the same operation must be blocked by idempotency.
