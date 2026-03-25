# AGENTS.md

## Purpose

This repository contains the backend foundation for "U zadnji čas".

Treat this codebase as a PHP 8+ modular monolith for a marketplace-style product where providers publish last-minute openings and clients can claim, book, and pay quickly.

Favor safe foundations, strong boundaries, and implementation-ready outputs over vague architectural brainstorming.

## Core architecture rules

- Target PHP 8+ only.
- Keep the backend framework-agnostic unless explicitly instructed otherwise.
- Use a modular monolith, not microservices.
- Use REST API design under `/api/v1`.
- Assume a relational database.
- Stripe payments and Stripe webhooks are first-class concerns.
- Async jobs are part of the design.
- Prefer maintainability and clarity over cleverness.

## Identity and auth boundary

- Shared SSO / shared identity backend owns registration and verification flows.
- OTP and phone verification are outside this backend.
- This backend consumes authenticated identity from upstream.
- This backend owns authorization, role mapping, profile linkage, and business permissions.
- Do not move business behavior into SSO.

## Actor model

- One global user can have multiple roles.
- Supported roles:
  - client
  - provider
  - admin
  - super-admin
- Provider must be modeled to support:
  - individual
  - organization

Build foundations that support both provider types, even if some flows are phased later.

## Locked business rules

- Providers publish last-minute openings / slots.
- Clients can reserve / book and pay for them.
- Clients cannot cancel.
- Client no-show is treated as consumed service.
- Provider no-show must trigger refund behavior.
- Admin accounts are provisioned internally.
- Login speed is critical for client booking / claim flow.

## Core engineering requirements

- Critical write flows must support idempotency.
- Stripe webhook ingestion must be deduplicated and retry-safe.
- Use DB transactions and locking where needed, especially around slot reservation.
- Include outbox pattern support at structural level.
- Include audit logging for sensitive operations.
- Keep API errors standardized.
- Keep module boundaries explicit.
- Separate MVP scope from extension points clearly.

## Expected core modules

- identity and access
- users and profiles
- provider management
- organization management
- service / offering catalog
- availability / openings
- booking
- payments
- refunds
- payouts
- cancellation and no-show handling
- notifications
- admin operations
- audit / logging
- webhook processing
- async job processing

## Naming and structure guidance

- Prefer clear business-oriented names.
- Keep module names, table names, DTOs, services, events, and endpoints consistent.
- Avoid dumping unrelated logic into shared helpers.
- Shared code should stay minimal and intentional.
- Favor explicit interfaces at module boundaries.
- Mark placeholders and extension points clearly.
- Do not present skeleton code as production-complete.

## API guidance

- Keep endpoints role-aware.
- SSO identity is assumed upstream, but business authorization is enforced here.
- Use predictable REST naming.
- Design for admin and operational visibility where relevant.
- Call out idempotent endpoints explicitly.
- Keep request / response contracts practical and implementation-ready.

## Database guidance

- Prefer normalized core tables.
- Include support structures where needed, such as:
  - idempotency keys
  - webhook event records
  - outbox messages
  - audit logs
  - job or job run tracking
- Define important unique constraints and indexes explicitly.
- Call out transactional and concurrency-sensitive areas.

## Output expectations for Codex

When generating docs, schema, API contracts, or starter code for this repo:

- Be concrete, not generic.
- Optimize for a small team building safe foundations first.
- Produce commit-ready markdown when writing docs.
- Produce realistic starter skeletons when writing code.
- Keep consistency across docs, schema, API, and code.
- Clearly distinguish:
  - locked decisions
  - assumptions
  - MVP scope
  - future extension points

## What to avoid

- Do not introduce microservices by default.
- Do not assume Laravel, Symfony, or another framework unless explicitly requested.
- Do not push business rules into SSO.
- Do not ignore no-show and refund rules.
- Do not flatten the whole backend into one unstructured layer.
- Do not generate abstract architecture that cannot be implemented.
- Do not hide important assumptions.

## Working style

For larger tasks:
- first summarize the locked foundation you are following
- then generate only the requested artifact batch
- keep naming aligned with earlier outputs
- avoid unnecessary rewrites of already locked decisions

If something is unspecified:
- make the safest practical assumption
- state it clearly
- keep it compatible with the locked architecture above
