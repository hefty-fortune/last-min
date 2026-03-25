# Auth and security

## Registration and verification model

Registration is based on verified phone identity.

- OTP is used for verification.
- OTP expiration and retry or lockout rules must be enforced.
- The phone number must remain globally unique.
- A verified phone is the basis for trusted identity creation.
- Admin accounts are not self-registered in the same way if the product defines internal provisioning rules.

## JWT and session model

Access control uses JWT-based authentication.

- JWT access tokens should be short-lived.
- Refresh tokens should be persistent, revocable, and stored securely.
- Session records should exist for traceability and revocation.
- Logout should revoke or invalidate refresh capability.
- Sensitive auth actions should be auditable.
- Device or session visibility can be added gradually if needed.

## Authorization matrix

### Core roles

- `client`
- `provider_member`
- `admin`
- `super_admin`

### Role notes

- A single global user may hold multiple roles.
- A user may be both a client and a provider-side actor.
- Provider-side authorization should depend not only on global role but also on membership in a specific provider account.

### Client permissions

A client can:

- Register and verify phone identity
- Log in and manage their own session
- View published slots
- Create a booking for an available slot
- View their own bookings
- View their own payment-related booking status where exposed

A client cannot:

- Create or edit provider slots
- Cancel bookings in the MVP
- Modify provider-owned data
- Trigger arbitrary refunds
- Access admin views

### Provider member permissions

A provider member can, within their own provider account and according to membership scope:

- View provider account operational data
- Create and edit provider configuration data
- Create, edit, publish, unpublish, and cancel slots
- View bookings for their own provider account
- Mark service outcomes such as `completed`, `provider_no_show`, or `provider_cancelled` where policy allows

A provider member cannot:

- Mutate another provider's data
- Grant themselves admin powers
- Inspect unrelated provider bookings
- Perform unrestricted manual refund operations unless explicitly allowed by policy

### Admin permissions

An admin can:

- Inspect bookings across operational scope
- Inspect payment and refund state
- Review audit records
- Trigger approved operational interventions
- Execute manual refund-related or booking-related actions where policy allows

An admin cannot:

- Bypass audit logging
- Silently mutate business state without trace
- Automatically act outside defined operational rules

### Super-admin permissions

A super-admin can:

- Perform all admin actions
- Manage broader system-level administrative functions
- Manage high-scope internal controls and exceptional interventions

## Ownership rule

Provider-side actions must be scoped by provider membership. Being a provider user is not enough by itself. The backend must verify that the acting user belongs to the target provider account and has the required permission level.

## Authorization design principle

Authorization should be policy-driven. The backend should centralize permission checks rather than scattering them inconsistently across controllers.
