# Operational and admin concerns

## Admin responsibilities

Admin capabilities must exist even before advanced tooling is polished.

- Admin must be able to inspect bookings.
- Admin must be able to inspect payment and refund status.
- Admin must be able to manually intervene in exceptional cases.
- Admin must be able to trigger manual refund when policy allows.
- Admin must be able to review audit history.
- Operational visibility is necessary from the early stages of the MVP.

## Audit logging policy

The following actions must write audit logs:

- Auth events
- Booking lifecycle transitions
- Payment and refund operational actions
- Admin manual actions

Audit logs should identify:

- Actor
- Action
- Entity
- Timestamp
- Relevant before and after context where appropriate

Audit logs are not optional technical decoration. They are part of system trust.

## Error handling and observability

Operational debugging should not depend on reading raw controller code.

The backend should support:

- A consistent API error format
- Request correlation or request IDs
- Traceability for payment and webhook failures
- Enough context in unexpected-failure logs to debug safely
- A clear distinction between business rule failures and system failures
