# Business functional requirements and user stories

## Authentication

Handles registration, phone OTP verification, session lifecycle, and role assignment for all three portals. Portals have a shared auth entry point and a shared identity backend. Speed at login is critical for the Client Portal because a re-auth prompt before claiming an opening is a lost sale.

### Functional requirements

#### AUTH-01 Phone-based registration and OTP verification

All users register using a verified mobile phone number via OTP. This applies to clients and providers registering via their respective portals. Admin accounts are provisioned internally by a super-admin and do not use the self-registration flow.

Rules
- OTP expires within 5 minutes, with a maximum of 3 attempts before a 15-minute lockout.
- Phone number is globally unique - one identity per number across all portals.
- OTP resend is allowed once per 60 seconds.
- Admin accounts are provisioned via super-admin invite only - no self-registration.
- On successful registration, the user is redirected to their portal home page - feed for clients, dashboard for providers.

Dependencies
- Notification module - SMS

#### AUTH-02 Session management and portal routing

Sessions are managed via JWT access tokens with a 15-minute TTL and refresh tokens with a 30-day TTL. Each portal maintains its own session context, and role-based routing is enforced server-side.

Rules
- Access token TTL is 15 minutes. Refresh token TTL is 30 days and rotates on each use.
- Each subdomain - admin, providers, app - scopes its own cookie with no cross-portal token sharing.
- Concurrent sessions are capped at 3 active devices per user per portal.
- Logout invalidates all refresh tokens for that portal session.
- Role-switch flow prompts re-authentication via OTP for the target portal.
- Session restore on page load must not render a login gate if the token is valid.

Dependencies
- None stated

#### AUTH-03 Role assignment and portal access control

Every user is assigned at least one role that determines which portals they may access. Access to the Admin Portal is restricted to ADMIN role holders exclusively.

Rules
- Roles are CLIENT, PROVIDER, and ADMIN.
- A user may hold CLIENT and PROVIDER simultaneously, and portals are navigable via role-switch.
- ADMIN role is provisioned internally only. No route exists to self-assign it.
- Role checks are enforced at API gateway level on every request, not only on portal load.

Dependencies
- Admin module

#### AUTH-04 Account recovery

Clients and providers can recover account access via their registered phone number. Admin account recovery requires super-admin intervention through a separate internal process not exposed in any portal UI.

Rules
- Recovery is verified via OTP to the registered phone and completes in under 2 minutes.
- Successful recovery invalidates all active sessions across both Client and Provider portals.
- An audit log entry is created with timestamp, IP address, and portal origin.
- Admin account recovery is an offline process and is not available via any portal UI.

Dependencies
- Notification module

### User stories

#### US-AUTH-01 Register and reach the live feed in under 60 seconds

As a new user on uzadnjicas.com, I want to complete phone registration, verify my OTP, and land on the live openings feed without any intermediate steps, so that I can immediately browse and claim available openings without losing time to setup.

Acceptance criteria
1. Given I visit uzadnjicas.com/register, then I see a phone number field and a Continue button with no other fields.
2. Given I submit my phone number, then an OTP SMS arrives within 10 seconds.
3. Given I enter the correct OTP, then my account is created and I am redirected to the live feed at /feed.
4. Given total time from page load to feed render exceeds 60 seconds, this is logged as a registration performance incident.
5. Given I already have an account, then the /register page offers a Log in instead link.

#### US-AUTH-02 Return to the live feed instantly as a logged-in user

As a returning client, I want to open uzadnjicas.com and be on the live feed within 2 seconds without a login screen, so that I can act immediately when I receive a notification about a new opening.

Acceptance criteria
1. Given I have a valid session cookie, then uzadnjicas.com loads /feed directly with no login redirect.
2. Given session restore takes longer than 200ms, this is flagged as a latency incident.
3. Given my session has expired, then I see a single OTP prompt and am returned to /feed immediately after verification.
4. Given I visit admin.platform.com with a CLIENT-only role, then I see a 403 page with no portal details.

## Provider

Manages provider onboarding, quality verification, service catalog, and subscription tiers. The Provider Portal is where providers complete onboarding, manage their profile, and track reputation. The Admin Portal surfaces the application queue and moderation tools.

### Functional requirements

#### PRV-01 Guided multi-step onboarding

Providers complete a structured 5-step onboarding process via the Provider Portal. The application enters an admin review queue on the Admin Portal upon completion. No provider may publish an opening until an admin approves the application.

Rules
- Onboarding steps are separate web pages:
  1. Business profile
  2. Service portfolio
  3. Pricing declaration
  4. Payout account - Stripe Connect
  5. Document upload
- All 5 steps are required before submission.
- Partial completion is saved server-side and resumes on next visit.
- Admin review queue shows applicant name, service category, submission date, and SLA countdown.
- Admin SLA is decision within 24 hours, with escalation for unanswered applications at the 20-hour mark.
- Approval transitions provider to ACTIVE and unlocks the Publish Opening button on the Provider Portal.
- Rejection requires a typed written reason.
- Rejected providers see the reason on their dashboard and via email.
- Rejected providers cannot reapply for 30 days, and a countdown is shown on the dashboard.

Dependencies
- Admin module
- Payment module

#### PRV-02 Subscription tier management

Providers subscribe to one of three tiers governing simultaneous active opening limits. Tier is also a trust signal displayed on the provider's public profile in the Client Portal.

Rules
- Tiers:
  - STARTER - 3 active openings
  - GROWTH - 15 active openings
  - ELITE - unlimited, with minimum 6-month track record
- Tier badge is displayed on provider profile cards visible in Client Portal search results.
- When the active opening limit is reached, the Publish Opening button shows Upgrade to publish more.
- Downgrade takes effect at billing period end and existing live openings are not affected.
- Admin Portal offers tier override on provider detail page with a mandatory audit note.

Dependencies
- Payment module
- Slot module

#### PRV-03 Service portfolio and pricing integrity

Providers maintain a service catalog via the Provider Portal. Each service has a verified base price that the platform uses to enforce opening pricing integrity. The Admin Portal allows flagged services to be reviewed.

Rules
- Each service requires name, category, duration in multiples of 15 minutes with max 480 minutes, and verified base price.
- Opening price must be within 10 percent of the service's verified base price and this is enforced at publish time.
- Services linked to past bookings cannot be deleted - archive only.
- Archived services are hidden from new opening creation.
- Admin may manually adjust a service's verified base price with an audit note.
- No discount framing, percentage-off labels, or promotional language is permitted in service descriptions.

Dependencies
- Slot module
- Admin module

#### PRV-04 Provider reputation score

Each provider's reputation score is computed from client reviews, completion rate, and cancellation history. It is displayed prominently on Client Portal feed cards and on the provider profile. The Provider Portal shows the full score breakdown.

Rules
- Score formula is:
  - verified client reviews x 0.6
  - completion rate x 0.3
  - response reliability x 0.1
- Score range is 1.0 to 5.0.
- Score is displayed only after a minimum of 5 completed bookings. Before that, a New provider badge is shown on the Client Portal.
- Score below 3.5 shows a warning banner on the Provider Portal dashboard and alerts admin.
- Score below 3.0 disables Publish Opening until manual admin review re-enables it.
- Provider may respond to client reviews on their profile, and those responses are visible on the Client Portal.

Dependencies
- Cancellation module
- Booking module

### User stories

#### US-PRV-01 Complete onboarding and get approved to publish

As a new service professional on providers.platform.com, I want to complete the 5-step onboarding flow and receive admin approval so I can publish my first opening, so that I can start monetizing my unused premium capacity through the platform.

Acceptance criteria
1. Given I visit providers.platform.com after registering, then I am directed to /onboarding/step-1 with a 5-step progress bar.
2. Given I complete step 2 and close the browser, then I return to /onboarding/step-3 on my next visit because progress is saved.
3. Given I complete all 5 steps and submit, then my dashboard shows Application under review with the 24-hour SLA countdown.
4. Given I am approved, then I receive an email and the Publish Opening button becomes active on my dashboard.
5. Given I am rejected, then I see the admin's written reason on my dashboard and a 30-day reapplication countdown.

#### US-PRV-02 Publish a new opening in under 30 seconds

As an active provider with a gap in my schedule, I want to publish a short-notice opening from my provider dashboard in under 30 seconds, so that my unused capacity goes live on the client feed before the window closes.

Acceptance criteria
1. Given I click Publish Opening, then I see a form pre-populated with my most recently used service and a time selector limited to the next 72 hours.
2. Given I select a service and time, then the price field defaults to my verified base price for that service.
3. Given I click Publish Now, then the opening appears on the Client Portal feed within 3 seconds.
4. Given I already have 3 live openings on STARTER tier, then the Publish Opening button shows Upgrade to publish more.
5. Given I try to set a price more than 10 percent above my verified base price, then the field shows an inline validation error.

#### US-PRV-03 Understand my reputation score breakdown

As an active provider whose score has recently changed, I want to see a clear breakdown of exactly what is contributing to my reputation score and what recent events changed it, so that I can take targeted action to improve my score and maintain a strong claim rate on my openings.

Acceptance criteria
1. Given I open /reputation, then I see my current score with three visible components - reviews 60 percent, completion rate 30 percent, reliability 10 percent.
2. Given I had a cancellation in the last 30 days, then it appears in a Recent events panel with the score impact shown.
3. Given my score is below 3.5, then a warning banner explains the consequence and links to admin support.
4. Given I respond to a client review, then my response appears publicly on the Client Portal within 5 minutes.

## Slot and opening

Providers publish openings via the Provider Portal. Clients discover them via the live feed on the Client Portal. Admins can monitor and manually intervene via the Admin Portal. The 72-hour window, real-time feed updates, and first-come-first-serve logic are non-negotiable platform constraints.

### Functional requirements

#### SLT-01 Short-notice opening creation

Providers publish openings exclusively via the Provider Portal. The creation form enforces the 72-hour maximum and 30-minute minimum lead time at the UI level. Out-of-range times are visually disabled, not only validated on submit.

Rules
- Opening must start within 72 hours of publication, with minimum lead time of 30 minutes from now. This is enforced at both UI and API level.
- Required fields are service from catalog, start time, capacity from 1 to 5, and price within 10 percent of base.
- Bulk creation and recurring patterns are not permitted. Each opening is a deliberate individual act.
- Provider may have a maximum of 3 simultaneously live openings across all tiers, with tier limits applied on top of this.
- After publishing, opening transitions immediately to LIVE and appears on the Client Portal feed within 3 seconds.

Dependencies
- Provider module - tier limits and pricing

#### SLT-02 Opening lifecycle state machine

Each opening progresses through a strict state machine. The Provider Portal surfaces current state and available actions for each opening. The Admin Portal allows state inspection and emergency intervention.

Rules
- States are DRAFT -> LIVE -> CLAIMED with partial capacity taken -> FULLY BOOKED or EXPIRED -> COMPLETED or CANCELLED.
- LIVE begins immediately on publish with no scheduled queuing.
- DRAFT is available but discouraged with the prompt Publish now for maximum visibility.
- Once any capacity unit is CLAIMED, provider-initiated cancellation requires admin approval.
- EXPIRED is an auto-transition if still unclaimed 30 minutes before appointment start.
- COMPLETED is an auto-transition 60 minutes after appointment end time.
- Admin Portal can force-transition an opening between states with a mandatory audit note.

Dependencies
- Booking module
- Cancellation module

#### SLT-03 Real-time client discovery feed

The Client Portal live feed is the primary product surface. It must feel alive and current, with every state change reflected within 3 seconds. The default sort is soonest appointment first.

Rules
- Feed shows only LIVE openings with remaining capacity greater than 0.
- Feed updates within 3 seconds of any opening state change and this is a hard SLA monitored through alerting.
- Fully booked openings are removed from the feed instantly and do not show as unavailable.
- Default sort is soonest appointment first.
- Secondary sorts are price low to high and provider rating high to low.
- Filters are service category, city or neighbourhood, and price range.
- No discount framing is allowed in filter labels.
- Each opening card displays provider name, service name, appointment time, duration, price, remaining capacity, and reputation score.
- No Save for later, Wishlist, or Remind me features exist.
- Feed must load its first visible cards within 1.5 seconds on a 4G connection.

Dependencies
- None stated

#### SLT-04 Opening price lock and integrity

Opening price is locked permanently at the moment it transitions to LIVE. No edits are possible after that point. This protects clients from bait-and-switch pricing and maintains trust in the feed.

Rules
- Opening price is locked at LIVE transition. The field becomes read-only in the UI and the API rejects price-change requests.
- Platform fee of 12 percent is calculated at publish time and shown to the provider as You will receive: $X after platform fee.
- Opening price must be within 10 percent of the service's verified base price at time of publication.
- No promotional, discounted, or percentage-off framing is permitted in opening titles or descriptions. This is enforced by content check at submission.
- Admin Portal flags price integrity violations in /compliance with provider detail and action options.

Dependencies
- Provider module
- Booking module

### User stories

#### US-SLT-01 Publish a live opening in 3 taps

As a provider on uzadnjicas.com, I want to publish an opening from my dashboard in 3 interactions with the UI, so that my unused capacity appears on the client feed before anyone else fills the same time gap.

Acceptance criteria
1. Given I click Publish Opening, then the form is pre-populated with the last-used service selected, the time picker open showing the next 2 hours, and price defaulting to verified base rate.
2. Given I confirm time and click Publish Now, then the opening appears on the client feed within 3 seconds and my /openings/live list updates.
3. Given the time I selected is outside the 30-minute to 72-hour window, then those times are greyed out and unselectable in the time picker.
4. Given I set a price 15 percent above my base rate, then an inline error appears before I can publish saying Price must be within 10 percent of your verified rate ($X).

#### US-SLT-02 See new openings appear on the feed in real time

As a client browsing uzadnjicas.com, I want to see new openings appear on my feed within seconds of a provider publishing them, so that I have the best possible chance of being first to claim a high-demand opening.

Acceptance criteria
1. Given a provider publishes an opening matching my city and service preference, then a card slides into my feed within 3 seconds without page refresh.
2. Given an opening I am viewing is claimed by another user, then the card disappears from my feed within 3 seconds.
3. Given I filter by Haircut category, then only haircut openings are shown and the feed still updates in real time.
4. Given the feed is empty with no live openings, then I see No openings right now - we'll notify you when one goes live.

#### US-SLT-03 Review and intervene on a flagged opening

As a platform admin, I want to see all openings flagged for pricing violations and act on them before they mislead clients, so that the feed maintains pricing integrity and clients trust that published prices are fair.

Acceptance criteria
1. Given a provider publishes an opening priced more than 10 percent above their base rate, then it appears in /compliance within 60 seconds.
2. Given I open the flagged opening, then I see the published price, the verified base rate, the percentage variance, and the provider's history.
3. Given I take down the opening, then it transitions to CANCELLED, the provider is notified with the reason, and no booking is affected.
4. Given I approve an exception, then the opening stays LIVE and I must enter an audit note explaining the exception.

## Booking

The booking or claim flow is the highest-stakes user interaction on the platform. On the Client Portal it must be as fast as possible, with sub-10-second confirmation. Providers see confirmed bookings on their dashboard. Admins access the full booking ledger for disputes and audits.

### Functional requirements

#### BKG-01 Atomic claim and instant payment capture

The Client Portal claim flow is a single linear path: opening detail to payment confirmation to booking confirmed. No step may be skipped and no step may be added. The flow is optimized for speed over comprehensiveness.

Rules
- Claim flow is opening detail -> one tap if saved card -> confirmation, with maximum 2 screens.
- Capacity lock is applied at the moment the client taps Claim and Pay, not on page visit.
- Payment Intent is created and captured within the same API call with no two-step authorize and capture flow.
- If payment fails, capacity lock is released within 10 seconds and client sees Payment failed - opening still available with retry.
- Claim-to-confirmation must complete in under 10 seconds at p95 and is monitored as a real-time SLA metric.
- Client cannot have more than 1 in-flight claim attempt at a time across the portal.

Dependencies
- Slot module
- Payment module

#### BKG-02 Booking confirmation and commitment record

A confirmed booking generates an immutable commitment record accessible to both client and provider. The confirmation is the legal and operational anchor for payout, cancellation, and disputes.

Rules
- Confirmed booking includes booking ID, client, provider, service, opening, date and time, price, platform fee, net payout, and payment intent ID.
- Booking record is append-only and no field is editable after CONFIRMED status.
- Both client and provider receive push notification within 10 seconds and SMS within 15 seconds.
- Confirmation page on the Client Portal must render within 1 second of CONFIRMED status.
- FAILED bookings show a failure screen with Try another opening CTA, and the opening feed card is highlighted if still available.

Dependencies
- Payment module
- Notification module

#### BKG-03 Booking history and export

All portals provide access to booking history appropriate to the user's role. Clients see their own bookings. Providers see all bookings across their openings. Admins see the platform-wide ledger.

Rules
- Clients see upcoming bookings sorted soonest first, past bookings sorted most recent first, and cancelled bookings with refund status.
- Providers see all bookings per opening, with client contact info visible after confirmation, and CSV export available.
- Admin Portal shows the full booking ledger with payment amounts, platform fees, payout status, and state history.
- Records are retained for a minimum of 7 years and all portals surface a record-retention disclaimer.
- Booking detail pages on all portals are accessible via direct URL and can be deep-linked from notifications.

Dependencies
- None stated

#### BKG-04 No-show recording and payout protection

Providers can record a client no-show via the Provider Portal within 30 minutes of appointment end. A no-show triggers full payout release and records a strike against the client's account.

Rules
- No-show can only be recorded within 30 minutes of appointment end time. The button is hidden outside this window.
- Recording a no-show triggers full payout to provider within 2 hours, no refund to client, and a no-show strike on the client record.
- After 2 no-show strikes, a warning badge appears on the client's profile visible to providers in the Provider Portal.
- After 3 strikes in 90 days, the client account is flagged for admin review in the Admin Portal.
- Client receives an automated email when a no-show is recorded.
- There is no dispute mechanism in v1.

Dependencies
- Payment module
- Payout module
- Admin module

### User stories

#### US-BKG-01 Claim an opening in 2 taps and receive instant confirmation

As a client with a saved payment method, I want to tap Claim Now, confirm with one more tap, and receive a booking confirmation within 10 seconds, so that I secure the opening before other users and have certainty the appointment is mine.

Acceptance criteria
1. Given I tap a feed card, then I land on the opening detail page at /openings/:id with provider info, price, and a large Claim and Pay button.
2. Given I have a saved default card, then the button shows Claim and Pay - Visa ending 4242.
3. Given I tap Claim and Pay, then I see a confirmation screen at /bookings/:id with all booking details within 10 seconds.
4. Given the opening was claimed by someone else between my tap and confirmation, then I see Opening no longer available - browse similar openings with a pre-filtered feed link.
5. Given payment fails, then I see a clear error and the opening remains available if capacity exists.

#### US-BKG-02 View and manage my upcoming bookings

As a client, I want to see all my upcoming bookings in one place with the information I need to prepare for each appointment, so that I never miss an appointment and know exactly where to go and who to see.

Acceptance criteria
1. Given I open /bookings, then I see my Upcoming bookings sorted by soonest appointment first.
2. Given I tap a booking card, then I see provider name, address with a map link, service, time, price paid, and a Cancel Booking button.
3. Given I tap Cancel Booking, then I see the exact refund I will receive calculated in real time before I confirm.
4. Given a booking is in the past, then it moves to the Past tab automatically and shows a Leave a review CTA.

#### US-BKG-03 See all bookings for my openings and export records

As a provider, I want to see all confirmed bookings across my openings and export them for my records, so that I can manage my schedule and maintain accurate financial records.

Acceptance criteria
1. Given I open /bookings on the Provider Portal, then I see all confirmed bookings grouped by opening with client name, service, time, and net payout.
2. Given a booking's appointment end time has passed and is within 30 minutes, then a Mark No-Show button is visible on that booking card.
3. Given I click Export CSV, then a file downloads with all bookings for the selected date range.
4. Given I tap a booking, then I see the client's contact details and the full booking record.

## Payment

Payment is handled entirely via Stripe. The Client Portal surfaces Stripe Elements for card entry and saved method selection. The Provider Portal shows fee transparency at publication and in the earnings dashboard. The Admin Portal provides fee configuration and refund override tools.

### Functional requirements

#### PAY-01 Immediate full-price capture at claim

One hundred percent of booking value is captured at claim confirmation. The Client Portal embeds Stripe Elements inline with no redirect to a Stripe-hosted page, preserving the seamless claim experience.

Rules
- Payment Intent is created and captured in a single API operation at claim time with no pre-authorize and capture split.
- Stripe Elements is embedded inline on the claim screen with no redirect to Stripe-hosted checkout.
- Apple Pay and Google Pay are offered on compatible browsers as the primary payment option.
- Failed capture is retried once automatically after 60 seconds. A second failure marks the booking FAILED.
- Platform fee of 12 percent is deducted at capture. Client pays gross price and provider receives net.
- No promo codes, discount fields, or voucher inputs are permitted on any payment screen.

Dependencies
- Booking module

#### PAY-02 Strict refund policy with client acknowledgement

The refund policy is prominently disclosed on the claim screen before payment. Clients must check an acknowledgement checkbox before the Claim and Pay button becomes active. The Admin Portal provides a refund override tool for exceptional cases.

Rules
- Refund tiers are:
  - More than 12 hours before start - full refund minus 12 percent platform fee
  - 4 to 12 hours - 50 percent refund
  - Less than 4 hours - no refund
- Client must check I have read and accept the cancellation policy before payment is processed.
- Platform fee is non-refundable in all scenarios, including provider-initiated cancellations.
- Provider cancellation gives client a 100 percent refund, and platform fee is recovered from the provider's next payout.
- Admin can issue a full refund override in Admin Portal with mandatory reason and audit log entry.

Dependencies
- Cancellation module
- Admin module

#### PAY-03 Saved payment methods for one-tap claims

Clients can save up to 3 payment methods for frictionless claiming. A saved default card enables a single-tap claim, which is the single most impactful conversion feature on the Client Portal.

Rules
- Up to 3 payment methods are stored as Stripe Customer PaymentMethod tokens.
- Default method is auto-selected on the claim screen, and client can switch inline without leaving the flow.
- One-tap claim is available when a default card is saved, with a single button tap capturing full payment.
- Saved methods are manageable at /account/payment-methods, and removal takes effect immediately.
- PCI DSS requirement is that no raw card data passes through platform servers - Stripe Elements only.

Dependencies
- Authentication module

#### PAY-04 Fee transparency for providers

Providers see the platform fee impact at every relevant point in the Provider Portal - at opening publication, in booking details, and in the earnings dashboard. There are no hidden deductions.

Rules
- Default platform fee is 12 percent of gross booking value.
- Fee is displayed to provider at publish time, in booking list, and in payout ledger.
- Admin Portal allows fee rate configuration per provider for strategic partners with a mandatory audit note.
- Platform-wide fee rate changes require 2-admin sign-off and 30-day advance notice to all providers.
- Fee structure change notices are delivered through Provider Portal dashboard banner and email.

Dependencies
- Admin module

### User stories

#### US-PAY-01 Pay for a claim with one tap using Apple Pay

As a client on a mobile browser, I want to complete payment using Apple Pay in a single biometric confirmation, so that I claim the opening in under 5 seconds before any competitor.

Acceptance criteria
1. Given I am on Safari on iOS with Apple Pay configured, then the claim screen shows an Apple Pay button as the primary payment option.
2. Given I have acknowledged the refund policy checkbox, then I tap the Apple Pay button and authenticate with Face ID.
3. Given Face ID succeeds, then I am on the booking confirmation page within 5 seconds.
4. Given Apple Pay is not available, then Stripe card entry is shown as a seamless fallback.
5. Given my saved Visa card is the default, then that card is shown if Apple Pay is not available, requiring only a single confirmation tap.

#### US-PAY-02 See exactly what I earn from each booking before I publish

As a provider filling in the opening publish form, I want to see my net earnings after platform fee update live as I type the opening price, so that I can make an informed pricing decision without doing mental arithmetic.

Acceptance criteria
1. Given I type a price into the price field, then the form shows Gross: $X, Platform fee 12 percent: $Y, You receive: $Z, updating in real time.
2. Given I set a price outside the plus or minus 10 percent range of my verified base rate, then an inline error appears and the Publish button is disabled.
3. Given I publish, then the booking detail page for that opening shows the same net earnings figure.
4. Given a booking is confirmed on my opening, then the booking card on /bookings shows the same gross, fee, and net breakdown.

## Payout

Payouts are released 12 hours after appointment completion via Stripe Connect. The Provider Portal surfaces a real-time earnings dashboard. The Admin Portal provides the platform-wide payout ledger and dispute management tools.

### Functional requirements

#### POT-01 12-hour post-completion payout

Provider net earnings are released 12 hours after the opening transitions to COMPLETED. Fast payouts are a provider retention mechanism because they incentivize repeat opening publication.

Rules
- Payout is released 12 hours after COMPLETED transition, with batch runs at 06:00 and 18:00 UTC.
- Minimum payout threshold is $15 net accumulated earnings.
- No-show payouts are released within 2 hours of no-show recording and are not subject to the 12-hour hold.
- If a dispute is filed within 12 hours, payout is held for 72 hours and the Provider Portal shows On hold - dispute open with expected release.
- Provider receives email on every payout event with exact net amount and bank reference.

Dependencies
- Slot module
- Booking module
- Payment module

#### POT-02 Stripe Connect payout account setup

Providers connect a verified bank account via Stripe Connect during step 4 of onboarding. Opening publication is blocked until this step is complete.

Rules
- Stripe Connect Express is used and provider is redirected to Stripe-hosted onboarding with no custom form.
- Opening publication is blocked until Stripe identity verification is fully complete.
- Providers can update payout account at /account/payout-settings, and changes apply from the next payout cycle.
- Admin Portal shows verification status on provider detail page and admins can trigger re-verification request.

Dependencies
- Provider module
- Payment module

#### POT-03 Real-time earnings dashboard and ledger

Providers track earnings in real time through the Provider Portal. Every booking confirmation updates the earnings dashboard within 60 seconds. Monthly PDF statements are downloadable.

Rules
- Earnings dashboard updates within 60 seconds of booking confirmation.
- Each ledger entry includes booking ID, gross, platform fee percent, platform fee amount, net, payout status, and timestamp.
- Pending earnings are shown separately from settled earnings with clear visual distinction.
- Monthly PDF statement includes all bookings, gross, fee, and net per booking, totals, and provider bank details.
- Ledger is append-only and retained for at least 7 years.

Dependencies
- Admin module

### User stories

#### US-POT-01 See my earnings update the moment a booking is confirmed

As a provider, I want to see a new booking appear in my pending earnings within 60 seconds of confirmation, so that I have real-time visibility into my income and can decide whether to publish more openings.

Acceptance criteria
1. Given a client claims my opening and payment is confirmed, then the booking appears in my /earnings Pending list within 60 seconds.
2. Given the booking is in the pending list, then I see gross amount, 12 percent fee deducted, net amount, and a countdown to release.
3. Given the payout batch runs and my payout is sent, then the status updates from Pending to Sent with a bank reference.
4. Given I open the booking that was just paid out, then I see a PDF download link for the statement including that booking.

#### US-POT-02 Download a monthly earnings statement for my accountant

As a provider, I want to download a PDF statement of all my bookings and payouts for a given month, so that I can provide accurate financial records to my accountant without manual data entry.

Acceptance criteria
1. Given I open /earnings, then I see a month selector defaulting to the current month.
2. Given I click Download Statement (PDF), then a PDF downloads immediately with all bookings, gross, fee, and net per booking, monthly totals, and my bank details.
3. Given a month had zero bookings, then the PDF still generates and shows zero totals with a note.
4. Given I need a prior month, then I can select any past month back to my account creation date.

## Cancellation

Cancellations are commitment failures. The Client Portal surfaces client cancellation with real-time refund calculation. The Provider Portal makes provider cancellation a deliberate, high-friction action. The Admin Portal enforces the strike system and handles late-cancellation approvals.

### Functional requirements

#### CAN-01 Client cancellation with real-time refund calculation

Clients cancel through the booking detail page on the Client Portal. Refund amount is calculated in real time based on time to appointment and shown before confirmation. Cancellation within 4 hours requires an additional confirmation step.

Rules
- More than 12 hours - full refund minus 12 percent platform fee, with exact dollar amount shown in modal.
- 4 to 12 hours - 50 percent refund, with exact dollar amount shown.
- Less than 4 hours - no refund, and UI adds a second destructive confirmation step requiring typed confirmation.
- Cancellation confirmation transitions booking to CANCELLED_BY_CLIENT and notifies both parties within 60 seconds.
- Cancelled booking moves to the Cancelled tab on /bookings immediately.

Dependencies
- Payment module
- Notification module

#### CAN-02 Provider cancellation with escalating penalty

Provider cancellation is a high-friction action on the Provider Portal designed to communicate the seriousness of the decision.

Rules
- Provider cancellation always triggers 100 percent full refund to all affected clients, and provider bears the platform fee.
- Reputation deduction is minus 0.3 per cancellation for the first 3 cancellations and minus 0.5 per cancellation for the fourth and later cancellations.
- Strike 1 triggers a dashboard warning banner.
- Strike 2 schedules a mandatory admin review call.
- Strike 3 triggers a 30-day publishing suspension.
- Late cancellation requests show opening details, affected bookings, and approve or deny actions with audit note.

Dependencies
- Payment module
- Provider module
- Notification module
- Admin module

#### CAN-03 Cancellation rate monitoring

Rolling 90-day cancellation rates are monitored for both providers and clients. Admin Portal alerts surface providers and clients approaching penalty thresholds.

Rules
- Provider cancellation rate above 5 percent shows a warning banner on Provider Portal dashboard.
- Provider rate above 10 percent triggers an Admin Portal alert and requires performance review before new openings.
- Provider rate above 20 percent triggers automatic publishing suspension pending admin review.
- Client no-show rate above 30 percent in 60 days flags the account for admin review in Admin Portal.
- Rates are calculated daily and thresholds are checked on each new cancellation event.

Dependencies
- Admin module
- Provider module

### User stories

#### US-CAN-01 Cancel a booking with full visibility of my refund

As a client who needs to cancel, I want to see the exact refund I will receive before I confirm the cancellation, so that I can make an informed decision and feel the process is transparent and fair.

Acceptance criteria
1. Given I tap Cancel Booking on a booking more than 12 hours away, then a modal shows You will receive $X back to your Visa ending 4242.
2. Given the appointment is 3 hours away, then the modal shows You will receive $0 - cancellations within 4 hours are non-refundable and requires me to type CANCEL.
3. Given I confirm, then the booking card moves to my Cancelled tab immediately and I receive an email within 60 seconds.
4. Given a refund applies, then the email includes the expected bank arrival date.

#### US-CAN-02 Cancel a committed opening only as a genuine last resort

As a provider facing an unavoidable emergency, I want to cancel a live opening while the portal makes the consequences impossible to miss, so that I act only when absolutely necessary and understand the full impact before confirming.

Acceptance criteria
1. Given I tap Cancel Opening, then a modal opens showing required reason dropdown, reputation score deduction of minus 0.3, and current strike count.
2. Given this is my second strike, then the modal prominently states This is your 2nd cancellation. A mandatory admin review call will be scheduled after this action.
3. Given I type CANCEL and confirm, then all affected clients receive 100 percent refund notifications within 5 minutes.
4. Given the appointment is within 4 hours, then the Cancel Opening button is replaced with Need to cancel? Contact support and there is no self-service path.
5. Given this is my third strike, then after confirming I see Your publishing rights have been suspended for 30 days and the Publish Opening button disappears.

## Admin

The Admin Portal at admin.platform.com is an internal-only web application used exclusively by platform operators. It is the trust enforcement layer. Every quality gate, configuration change, and moderation action flows through here. It is utilitarian in design, favoring dense data tables, queues, and audit trails over aesthetics.

### Functional requirements

#### ADM-01 Provider application review queue

The primary daily task in the Admin Portal is reviewing provider applications. The /queue page is the first screen an admin sees after login.

Rules
- Queue is sorted by submission date ascending - oldest first for SLA reasons.
- Each application shows provider name, category, submission date, SLA countdown, and completeness indicator.
- Application detail page includes inline document viewer for PDF or image, verification checklist, and approve, reject, or request-more-info actions.
- Approve transitions provider to ACTIVE, sends activation email, and creates audit log entry.
- Reject requires typed written reason, sends rejection email with reason, and enforces 30-day reapplication lockout.
- Request more info sends email with admin note, keeps application in PENDING state, and does not reset SLA.
- Applications unreviewed at 20 hours are highlighted amber.
- Applications unreviewed at 23 hours are highlighted red and trigger senior-admin notification.

Dependencies
- Provider module
- Notification module

#### ADM-02 Platform configuration and scarcity parameters

Admins control scarcity and fee parameters via the settings page in the Admin Portal. Changes to critical parameters require 2-admin sign-off.

Rules
- Configurable fields are max opening lead time at 72 hours, min lead time at 30 minutes, max concurrent openings per provider, platform fee percent, payout hold hours, and no-show recording window.
- All changes are versioned with field name, old value, new value, admin ID, timestamp, and justification note.
- Platform fee percent and opening window max require 2-admin sign-off. First admin submits and second admin approves from pending state.
- Config history page at /settings/history is immutable and searchable.
- Test-cohort mode can apply changes to a named provider cohort before full rollout, marked as v1.1 scope.

Dependencies
- Payment module
- Payout module
- Slot module

#### ADM-03 Commitment failure enforcement and moderation

Admins investigate and resolve commitment failures flagged by the system, including provider strikes, client no-show patterns, late-cancellation requests, and reputation disputes.

Rules
- Enforcement feed shows event type, entity name, timestamp, severity as info, warning, or critical, and action buttons.
- Provider detail page at /providers/:id shows full profile, application history, all openings, all bookings, cancellation history, current strike count, and reputation score timeline.
- Suspend provider blocks publishing immediately, notifies provider with reason, and cancels all live openings with client refunds.
- Ban provider anonymizes PII after 30-day grace period and blocks re-registration by phone number.
- Client detail page at /users/:id shows booking history, no-show record, and cancellation history.
- All enforcement actions create immutable audit log with admin ID, timestamp, action taken, and reason.

Dependencies
- Authentication module
- Cancellation module
- Notification module

#### ADM-04 Platform health and network dashboard

Admins monitor supply and demand health and trust metrics through a dedicated dashboard. The dashboard is metrics-first, not action-first, and surfaces anomalies for investigation.

Rules
- KPIs include active providers, openings published today, over 7 days, and over 30 days, average time to claim, claim rate percent, and cancellation rate by side.
- Trust metrics include average provider reputation score, score distribution histogram, and count below 3.5.
- City-level view shows supply as live openings versus demand as active clients per city.
- Alerts trigger when claim rate is below 60 percent, average time to claim is above 2 hours, or providers below 3.5 exceed 5 percent of active providers.
- All metrics are exportable as CSV.
- Date range selector offers 7 days, 30 days, 90 days, and custom.

Dependencies
- Booking module
- Payment module
- Payout module

### User stories

#### US-ADM-01 Review a provider application as a quality gate decision

As a platform admin, I want to open a provider application, review all submitted documents inline, and make an approval decision within the 24-hour SLA, so that only genuinely premium providers are admitted to the network.

Acceptance criteria
1. Given I log in to admin.platform.com, then I land on /queue showing all pending applications sorted by submission date.
2. Given I open an application, then I see a split-screen with document viewer on the left and verification checklist on the right.
3. Given I check all checklist items and click Approve, then the provider receives an activation email within 5 minutes and disappears from my queue.
4. Given I click Reject, then I am required to type a rejection reason before the action completes.
5. Given an application has been in queue for 20 hours, then its row is highlighted amber and I have an Escalate button.
6. Given I click Request More Info, then an email is sent to the provider with my note and the application remains in my queue.

#### US-ADM-02 Handle a provider's second-strike cancellation

As a platform admin, I want to be automatically notified when a provider hits their second cancellation strike and schedule a mandatory review call, so that I intervene before the provider reaches a third strike and a 30-day suspension.

Acceptance criteria
1. Given a provider records their second cancellation, then a Strike 2 - Mandatory review required card appears in my /enforcement feed within 5 minutes.
2. Given I open the card, then I see the provider's full cancellation history, including dates, reasons, affected clients, and refund amounts.
3. Given I schedule a review call through the action panel, then the provider receives a calendar invite with mandatory attendance notice.
4. Given the provider does not respond within 48 hours, then their publishing rights are suspended automatically and I receive a notification.

#### US-ADM-03 Change the platform fee rate with dual-admin sign-off

As a platform admin who initiates the change, I want to propose a platform fee rate change that requires a second admin to approve before it takes effect, so that critical financial parameters cannot be changed unilaterally.

Acceptance criteria
1. Given I edit the platform fee percent on /settings and submit, then the change enters Pending approval state and the current rate remains unchanged.
2. Given a second admin logs in and sees the pending change on /settings, then they see current rate, proposed rate, submitting admin, and justification note.
3. Given the second admin approves, then the new rate takes effect immediately for all future bookings and all providers receive a notification email.
4. Given the second admin rejects, then the change is discarded and both admins see the rejection reason in /settings/history.

## Notifications

All notifications are operational, not promotional. The most critical notification is the new-opening push alert to clients because it drives claim velocity. The Provider Portal and Admin Portal surface notification delivery status and preference management.

### Functional requirements

#### NOT-01 New opening push alert to clients

When a new opening is published matching a client's city and service preferences, they receive a browser push notification or SMS within 10 seconds. This is the platform's primary re-engagement mechanism.

Rules
- Push notification is sent within 10 seconds of opening transitioning to LIVE.
- Notification body is {Provider name} just published a {service} opening at {time} - {price}. Claim now.
- Deep link in notification opens /openings/:id directly. If opening is already claimed, it shows provider profile instead.
- Client configures preferences for city as required and service categories as multi-select with all selected by default.
- Push permission request is shown on first Client Portal visit after login and is non-blocking if denied.
- SMS fallback is sent within 30 seconds if push is undelivered or permission is denied.
- No opening alerts are sent if the client already has a confirmed booking in the same time window.

Dependencies
- Slot module
- Authentication module

#### NOT-02 Booking confirmation and appointment reminders

Booking confirmation and appointment reminders reinforce commitment. Reminders include current cancellation status to discourage last-minute cancellations.

Rules
- Booking confirmation is push plus SMS to client within 10 seconds and push plus SMS to provider within 15 seconds.
- Client reminder at 6 hours before appointment includes provider address, current refund status, and cancellation policy.
- Client reminder at 1 hour says Your appointment is in 1 hour. Cancelling now means no refund, with booking detail link.
- Provider reminder at 2 hours includes client name, service, arrival time, and client contact details.
- All reminders deep-link to booking detail page in the appropriate portal.

Dependencies
- Booking module

#### NOT-03 Commitment failure and operational alerts

Cancellations, no-shows, score changes, and strike events generate immediate operational notifications to all affected parties.

Rules
- Provider cancellation triggers client push plus SMS within 60 seconds with 100 percent refund confirmation and feed link.
- No-show recorded triggers provider push confirming payout release within 2 hours.
- Score drop below 3.5 triggers provider push plus email with remediation guidance and /reputation link.
- Strike 2 event triggers admin push plus email and provider push with mandatory review notice.
- Cancellation refund triggers client email with exact refund amount and expected bank arrival date.

Dependencies
- Cancellation module
- Booking module
- Admin module

#### NOT-04 Notification preferences and delivery tracking

Clients configure opening alert preferences on the Client Portal. Core operational notifications such as confirmations, reminders, and cancellation alerts cannot be disabled. The Admin Portal surfaces delivery failures.

Rules
- Configurable settings are opening alert city, service category filter, and optional SMS reminders.
- Non-configurable and locked items are OTP SMS, booking confirmations, and cancellation alerts.
- Delivery statuses are QUEUED, SENT, DELIVERED, and FAILED, tracked per notification.
- Failed notifications are retried once after 30 seconds.
- Admin Portal alert is raised on persistent failure, defined as more than 5 failures in 1 hour.
- Delivery logs are retained for 90 days and visible in Admin Portal at /notifications/delivery.

Dependencies
- Authentication module
- Admin module

### User stories

#### US-NOT-01 Receive a push notification and claim an opening in under 60 seconds

As a client who has granted push permission, I want to receive a push notification about a new opening and complete the claim before other users, so that I can access premium appointments that would normally take weeks to book.

Acceptance criteria
1. Given a provider publishes an opening matching my city and category, then I receive a push notification within 10 seconds.
2. Given I tap the notification, then I land on /openings/:id with the Claim and Pay button visible.
3. Given I have a saved default card, then I can complete the claim in one tap from the opening detail page.
4. Given the opening was claimed between notification send and my tap, then /openings/:id shows Opening no longer available - see similar openings.
5. Given I denied push permission, then I receive an SMS fallback within 30 seconds.

#### US-NOT-02 Receive a reminder that makes me think twice before cancelling

As a client with a confirmed booking 6 hours away, I want to receive a reminder that shows me exactly what I would lose if I cancelled right now, so that I arrive for the appointment rather than cancelling impulsively.

Acceptance criteria
1. Given my appointment is 6 hours away, then I receive a push notification saying Your appointment is in 6 hours. Cancel now and receive $X back.
2. Given I tap the notification, then I land on /bookings/:id showing the current refund status prominently as Cancel now: 50 percent refund ($X).
3. Given the appointment is 45 minutes away when I check, then the booking detail shows Cancel now: no refund applies.
4. Given I tap Cancel Booking after seeing the no-refund warning, then a second confirmation step requires me to type CANCEL.

#### US-NOT-03 Configure which opening alerts I receive

As a client, I want to set my city and preferred service categories so I only receive opening alerts relevant to me, so that I am not overwhelmed with irrelevant notifications and only act on openings I genuinely want.

Acceptance criteria
1. Given I open /account/notifications, then I see a city selector that is required and a service category multi-select.
2. Given I select Barbering and Massage as my categories, then I only receive opening alerts for those services.
3. Given I try to toggle off Booking confirmations, then the toggle is locked and a tooltip explains it is required.
4. Given I update my preferences, then changes take effect on the next opening published after saving.
