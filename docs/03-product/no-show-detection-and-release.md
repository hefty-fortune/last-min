# No-show detection & slot release

> Status: working spec — captured 2026-07-25 from product discussion.
>
> This mechanism is the core differentiator of U zadnji čas and was previously undocumented. The earlier docs described the *problem* (cancellations and no-shows create perishable premium capacity) and the *goal* (structured monetization), but not *how* openings are actually generated. This document fills that gap. Sections marked **LOCKED** are decided; §10 lists what is still open.

## 1. Why this exists

U zadnji čas monetizes perishable premium capacity — tables, chairs, appointment slots — otherwise lost to no-shows and last-minute cancellations.

Rather than wait for a provider to notice a gap and manually publish it, the platform **proactively detects likely no-shows in the provider's existing bookings** and turns them into sellable last-minute openings. This is the engine behind the strategy doc's promise of a "structured, monetized, and scalable alternative" to filling cancellations by Instagram or phone.

## 2. How an opening is generated — LOCKED

Two sources:

1. **Predicted no-show** — the confirmation loop (§3) flags a booked guest as unlikely to attend.
2. **Provider manual flag** — the provider directly marks a slot for last-minute resale.

## 3. The confirmation loop (no-show detection)

### Reconfirm-or-release — core mechanic, LOCKED

Reservations are **conditional on reconfirmation**. At booking, the guest is told: reconfirm by the stated deadline or the slot may be released.

This converts "guessing a no-show" into "the guest failing a stated, agreed condition," and matches the brand's *controlled scarcity with strict commitment*. Side benefit: it reduces no-shows outright — valuable to the provider even when nothing is resold (see §8).

### Escalation ladder

Signal strength escalates: **SMS → email → human phone call.** Cold-start is deliberately human-heavy (calls) to build both prediction accuracy and the guest confirmation habit; automation increases as confidence data accrues.

### Explicit response

Ask for an explicit answer ("Reply C to keep, X to release"), not merely "reply to confirm." Silence *after* an explicit opt-out was offered is far stronger evidence than silence alone.

### Timing

The loop runs inside a **pre-set, per-provider window** before the appointment. When it starts and the reconfirmation deadlines are provider configuration.

### Confidence scoring

Per-guest and per-venue reconfirm→attended history builds a no-show risk score. Start conservative (human calls, provider review); auto-settle is *earned* only where the data proves the threshold.

## 4. Release & settlement — LOCKED

- **Settlement modes (provider toggle):** *auto-settle by us*, or *provider review* before release.
- **VIP exemption:** the provider can flag guests as **never-release**; their slots are excluded from the resale pool regardless of (non-)confirmation. They may still receive a soft confirmation for the no-show benefit.
- **Provider prices the freed slot** via a pre-set rule / floor — they are mid-service, so near-instant rules beat manual decisions.
- **Collision-safety net (applies regardless of confidence):**
  - a grace buffer / point-of-no-return before release,
  - a provider final-gate for high-value slots,
  - a defined "both showed" recovery protocol (§5).

## 5. Collisions & who bears the risk — LOCKED

A **collision** = a released-and-resold slot where the original guest shows up after all.

**U zadnji čas absorbs every collision. The venue is always harmless.** On a collision: the venue seats its original guest at no cost; U zadnji čas fully refunds the new buyer and provides a genuine make-good (credit / priority on the next drop / comp). The rare cost is budgeted into commission economics.

Rationale: providers will only release inventory if the downside is never theirs. Venue-harmlessness is the trust guarantee that unlocks the supply side.

### Collision accountability — anti moral-hazard, LOCKED

Venue-harmless means harmless *outcome*, not zero *accountability*. A single collision is always the platform's to absorb; a venue's collision **rate** is theirs to answer for. Otherwise a venue with no per-collision cost is incentivized to over-release (or overbook) at the platform's expense.

- **Metric:** collision rate = (resold slots where the original showed) ÷ (resold slots), on a rolling window. Slots released but never resold do not count — we penalize actual harm, not noise.
- **Escalating, mostly-operational consequence ladder:**
  - 🟢 **Green** (proven clean): full auto-release, higher volume caps, faster payout.
  - 🟡 **Yellow** (rate over threshold): forced provider-review mode, wider grace buffer, throttled release volume, coaching nudge.
  - 🔴 **Red** (sustained pattern): release privileges suspended — still a normal provider, can still use the confirmation loop, but cannot resell until earned back.
  - First consequences are **operational** (lose automation → volume → privilege). **Financial penalty is reserved only for demonstrable gaming**, handled as a terms-of-service violation, not a routine dial — this preserves the clean venue-harmless promise for good-faith venues.
- **Probation cap:** new venues start in review mode with a low release ceiling, ramping only as their rate stays clean — bounding platform exposure while both the venue and the confidence model are least calibrated.
- **Gaming signatures (extra scrutiny, especially on manual-flag releases):** slots that repeatedly collide (especially high-value), manual flags on slots that never failed reconfirmation, or release volume above plausible no-show rates (an overbooking signature).

## 6. Buyer side — pre-warmed demand pool

To make the tight release→sale window workable, maintain a **standing demand pool**: users register interest ("notify me if a table at Venue X frees up tonight"), ideally with **pre-authorized payment**, so a freed slot matches instantly to ready-to-pay demand instead of a cold search.

## 7. Running the loop ourselves — GDPR architecture

The platform runs the confirmation loop itself; it cannot rely on providers' systems to do it. To keep this lawful:

- **U zadnji čas is the data *processor*; the provider is the *controller*** (the provider holds the lawful basis — the booking is a contract with their guest). Governed by a **Data Processing Agreement**.
- Messages are **provider-branded and transactional** — "[Venue]: please reconfirm your reservation" — i.e. service communication about an existing booking, **not marketing**.
- **Data minimization**; a **strict wall** (guest data is never used to market U zadnji čas without separate opt-in); **short retention** (purge after the appointment window).
- The **reconfirm-or-release disclosure at booking** doubles as the transparency notice.
- **Requires Croatia/EU privacy-lawyer sign-off before launch.** The architecture above is the defensible path — not a legal opinion.

## 8. Standalone confirmation loop — go-to-market wedge

Offer the confirmation / no-show-reduction loop as a **standalone product** to venues not yet reselling:

- Easier first "yes" — obvious upside, no collision risk.
- Builds the assets resale needs *before* resale: the data pipe, the guest habit, the confidence data.
- Independently valuable (no-shows are costly) and sticky (embedded in operations).
- Acts as a live, zero-risk calibration period for the prediction engine.

This is the natural **Phase 1**: prove the loop and the numbers first; switch on release-and-resell once collisions are proven rare.

## 9. Integration implications (corrects earlier research)

The real requirement from a provider's system is **read access to upcoming bookings + guest contact details + permission to message those guests** — *not* availability / free-busy sync.

The earlier connector-feasibility research targeted availability APIs and must be re-run against this actual need (upcoming bookings + contacts + GDPR-compatible outreach permission). See the integration-feasibility findings for what carries over.

## 10. Locked vs open

**Locked (this session):**

- Openings from predicted-no-show *or* provider manual flag.
- Reconfirm-or-release default; VIP never-release exemption.
- Settlement toggle: auto-settle vs provider-review.
- U zadnji čas absorbs collisions; venue-harmless.
- Pre-set per-provider timing and pricing rules.
- Platform runs the loop as a provider-branded processor (pending legal sign-off).
- Standalone loop as the Phase-1 wedge.
- Collision accountability: rate-based, escalating operational consequences (green/yellow/red) + new-venue probation cap; financial penalty only for demonstrable gaming.

**Open — to pressure-test:**

- Demand-side liquidity at cold-start (finding a buyer inside the window early on).
- Guest confirmation-fatigue on premium relationships.
- Human-call unit economics at scale.
- Auto-settle confidence threshold vs. collision rate.
- Phase-1 source of booking data before any system integration exists.
