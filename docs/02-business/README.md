# Business model and economics

### Revenue model overview

U zadnji čas operates on a commission-based revenue model combined with optional provider subscription tiers.

The platform captures payment from the user at the time of booking.

A commission of 15-20% is deducted from each successful transaction.

The remaining amount is paid out to the provider after the appointment time has passed and any review window has expired.

Commission is always applied and is not reduced by subscription tiers.

Subscription revenue is additional and provides visibility and operational advantages to providers.

The economic structure prioritizes transaction volume, reliability, and platform-controlled payments.

### Commission structure

The platform applies a 15-20% commission on every completed booking.

Commission is deducted automatically before provider payout.

Commission applies regardless of whether the provider uses a subscription tier.

Commission incentivizes the platform to maximize successful bookings and minimize cancellations.

Commission level must balance provider attractiveness and platform sustainability.

The final percentage can be optimized after observing early provider behavior.

### Subscription model

The subscription model provides tiered advantages to providers.

Subscription does not reduce commission percentage.

Subscription tiers may include combinations of the following: • Priority listing placement • Featured slot exposure • Analytics dashboard access • Enhanced brand profile customization • Faster payout cycles • Increased slot publishing limits • Promotional boosts Subscription serves to increase provider lifetime value without distorting core transaction economics.

Subscription pricing should reflect premium positioning and avoid signaling discount-market behavior.

### Payment and payout flow

The platform uses Stripe to process all user payments.

Payment is captured immediately at the moment of booking confirmation.

Funds are held by the platform until the appointment time has passed.

An optional short review window may be included before payout is triggered.

After completion, funds are transferred to the provider minus commission.

If the provider cancels before the appointment, the user is automatically refunded.

Stripe integration allows idempotent payment handling and concurrency-safe booking confirmation.

Platform-controlled payments ensure enforceability of refunds and commission collection.

### Cancellation economics

User cancellation results in no refund.

The provider receives payout after commission deduction if the appointment is not fulfilled due to user absence.

Provider cancellation results in automatic refund to the user.

Provider cancellation increases visible cancellation metrics.

No financial penalty is applied in MVP, but the architecture must allow it in future.

Strict no-refund policy for users protects scarcity value of time-sensitive slots.

Cancellation discipline is central to marketplace trust.

### Unit economics (per booking logic)

For each booking: User pays full slot price.

Platform retains 15-20% commission.

Provider receives 80-85% after payout trigger.

Platform revenue scales with: • Booking volume • Average slot price • Subscription uptake Costs include: • Stripe transaction fees • Hosting and infrastructure • Marketing acquisition • Customer support • Chargeback and refund risk Early phase focus should be on high average booking value to maintain healthy margins.

### Risk and fraud considerations

Strict FCFS model creates race conditions that must be technically controlled.

Payment must be idempotent to avoid double-booking.

Provider cancellation abuse must be monitored.

User no-show disputes must be handled simply in MVP but extensibly in architecture.

Chargebacks represent financial risk and require clear Terms of Service.

High-end positioning reduces likelihood of low-value fraud but does not eliminate it.

Trust signals and reliability metrics reduce long-term platform risk.

### Provider incentives

Providers gain monetization of otherwise unused premium capacity.

Providers receive guaranteed payment for user no-shows.

Providers gain exposure to high-intent customers.

Subscription tiers allow marketing advantage without reducing commission.

Visible reliability rating incentivizes providers to avoid cancellations.

Platform payout timing ensures provider confidence in receiving funds.

### Long term economic extensions

Future financial penalty system for provider cancellations may be introduced.

Dynamic pricing or bidding mechanisms may increase slot monetization.

Platform-native wallet or credit system may improve liquidity control.

Integration with external booking systems may increase inventory volume.

Advanced analytics may increase provider retention and subscription conversion.

Multi-city expansion increases network effects and revenue scale.
