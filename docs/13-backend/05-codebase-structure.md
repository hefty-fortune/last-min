# Codebase structure

## Folder structure

A suggested high-level project structure:

```text
src/
  Shared/
  Domain/
  Application/
  Infrastructure/
  Interfaces/
  Modules/
    Identity/
    Providers/
    Slots/
    Bookings/
    Payments/
    Refunds/
    Notifications/
    Admin/
    Audit/
bootstrap/
config/
database/
public/
bin/
tests/
```

Additional structure rules:

- Each module should keep its own layers.
- Shared code should be minimal and intentional.
- Infrastructure-specific code should not dominate the domain model.

## Layer responsibilities

This structure should help developers understand where code belongs.

- `Domain` contains entities, value objects, domain rules, and invariants.
- `Application` contains use cases, command and query handlers, DTOs, and orchestration logic.
- `Infrastructure` contains persistence, Stripe integration, email transport, and other external technical details.
- `Interfaces/Http` contains controllers, requests, and response mapping.

The following boundaries should remain strict:

- Domain should not know HTTP details.
- Domain should not know database driver details.
- Controllers should not become the home for booking or payment business logic.

## Dependency injection and bootstrapping

The app should be wired in an explicit and centralized way.

- Dependency injection should be centralized and explicit.
- League Container or an equivalent should be used consistently.
- Service registration should be modular.
- Configuration should be environment-driven.
- Entry point bootstrapping should stay thin.
- Modules should expose wiring without forcing global coupling.
