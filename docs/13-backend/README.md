# Backend

Backend documentation extracted and normalized from the uploaded backend section PDFs.

## Contents

- [01 Overview and direction](01-overview-and-direction.md)
- [02 Product flow](02-product-flow.md)
- [03 Domain model](03-domain-model.md)
- [04 State machines](04-state-machines.md)
- [05 Codebase structure](05-codebase-structure.md)
- [06 Auth and security](06-auth-and-security.md)
- [07 API and integration](07-api-and-integration.md)
- [08 Data integrity and safety](08-data-integrity-and-safety.md)
- [09 Database design](09-database-design.md)
- [10 Operational and admin concerns](10-operational-and-admin-concerns.md)
- [11 Delivery plan](11-delivery-plan.md)
- [12 Open questions](12-open-questions.md)

## Notes

- This backend is designed as a modular monolith in plain PHP.
- PostgreSQL is the primary database.
- Authentication is handled by the platform's own verified phone-based identity flow with JWT-based sessions.
- The MVP centers on fixed-price last-minute slots, immediate payment, strong booking integrity, auditability, and foundation-first architecture.
