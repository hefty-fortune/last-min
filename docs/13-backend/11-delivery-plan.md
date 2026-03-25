# Delivery plan

## Implementation phases

The backend implementation order is:

- Phase 0 - conventions, project structure, config patterns, error format, and migration approach
- Phase 1 - identity and auth foundation
- Phase 2 - provider backbone
- Phase 3 - slots
- Phase 4 - booking hold and concurrency protections
- Phase 5 - Stripe payment integration
- Phase 6 - refund basics
- Phase 7 - notifications and outbox usage
- Phase 8 - admin visibility and audit tooling

## Suggested dev backlog

Practical starting work for developers:

- Set up the project skeleton and DI wiring
- Create base migration conventions
- Implement user and phone verification tables
- Implement JWT and refresh token flow
- Implement provider account structure
- Implement slots schema and CRUD foundations
- Implement bookings with hold logic
- Implement active booking uniqueness protections
- Implement webhook event storage and processing skeleton
- Implement audit logging service or middleware foundations
