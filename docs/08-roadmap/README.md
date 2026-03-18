# Milestones and roadmap

### Roadmap overview

The roadmap defines the staged development and launch plan for the platform.

Development will focus first on a functional MVP capable of supporting real transactions.

The MVP must validate the core marketplace assumptions: provider supply, user demand, and reliable booking mechanics.

Later phases will expand product capabilities and marketplace scale.

The roadmap prioritizes simplicity, speed of development, and operational learning.

Each milestone introduces a clear set of functional capabilities.

### Phase 1 - Core platform foundations

Phase 1 focuses on building the essential platform infrastructure.

> Note: this phase is both mobile (users) and web (providers and admin) ?

User authentication and phone verification must be implemented.

Provider accounts and onboarding workflows must be created.

> Note: Can provider have multiple internal users assigned?

Basic provider portal functionality must allow slot creation and publishing.

Slot browsing must be available for users.

Core database schema must support users, providers, slots, bookings, and payments.

> Note: Maybe add feedbacks/review as a separate entity? Then we can collect data on user experience

and provide further metrics.

Stripe payment infrastructure must be integrated.

This phase establishes the technical foundation of the platform.

### Phase 2 - Booking and payment engine

Phase 2 focuses on enabling reliable booking and payment transactions.

Users must be able to initiate booking and complete payment.

Atomic booking logic must prevent double-booking of slots.

Stripe payment capture must occur at booking confirmation.

Booking records must be created and stored reliably.

Users and providers must receive booking confirmation notifications.

Successful completion of this phase enables real marketplace transactions.

### Phase 3 - Payouts and cancellation handling

Phase 3 introduces payout management and cancellation enforcement.

Providers must receive payouts after appointment time passes.

Payout scheduling logic must be implemented.

Provider cancellations must trigger automatic refunds.

Cancellation metrics must be tracked and recorded.

Administrative monitoring tools must allow visibility into booking activity.

This phase ensures financial flows are stable and trustworthy.

### Phase 4 - Marketplace operations

Phase 4 introduces operational tooling for managing the marketplace.

Provider approval workflow must be available.

Provider reliability metrics must be visible to administrators.

Customer support procedures must be defined.

Basic operational dashboards may be introduced.

This phase enables sustainable marketplace management.

### Phase 5 - UX refinement and trust signals

Phase 5 focuses on improving user and provider experience.

Booking flow clarity must be optimized.

Provider reliability indicators may be displayed.

Confirmation and reminder notifications must be refined.

Visual identity must be polished to match premium positioning.

This phase improves trust and perceived product quality.

### Phase 6 - Subscription and provider monetization

Phase 6 introduces provider subscription tiers.

Subscription management must be integrated with the provider portal.

Tier benefits such as priority listing and analytics must be implemented.

Subscription billing must be handled through Stripe.

Subscription adoption increases provider lifetime value.

### Phase 7 - Launch preparation (Zagreb)

Phase 7 focuses on preparing the marketplace for launch.

Initial provider partners must be onboarded.

Operational procedures must be tested.

Payment and payout flows must be validated.

Customer support readiness must be ensured.

Marketing messaging must align with premium positioning.

The platform is launched initially in Zagreb.

### Phase 8 - Post launch iteration

Post-launch development focuses on learning from real user behavior.

Marketplace metrics must be monitored closely.

Provider feedback must inform product improvements.

UX refinements must address friction in booking flow.

Operational processes must be optimized.

This phase validates product-market fit.

### Phase 9 - Future and much more…

Future expansion may introduce bidding mechanics.

Additional service categories may be supported.

External booking system integrations may increase inventory.

Financial penalties for provider cancellations may be introduced.

Expansion to additional cities may be planned.

The platform may evolve into a broader premium last-minute service network.
