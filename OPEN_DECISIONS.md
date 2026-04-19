# Open Decisions — Hyperlocal Partnership Network

> Every item here BLOCKS the named module from starting.
> Must be LOCKED before that module's first migration or controller is written.
> Review and update at the start of every session.

---

## Legend
- 🔴 BLOCKS build — must be decided before writing code
- 🟡 PENDING — decision needed but not yet blocking
- 🟢 LOCKED — decided, recorded, build can proceed

---

## Decision Log

---

### D-001 — MySQL environment for local dev
**Blocks:** All migrations
**Status:** 🟢 LOCKED — Laravel Herd (PHP) + DBngin (MySQL GUI) + terminal for artisan commands

Setup:
1. Install **Laravel Herd** (free): https://herd.laravel.com — handles PHP 8.2, Nginx, site DNS automatically
2. Install **DBngin** (free): https://dbgin.com — one-click MySQL 8 instance, no terminal needed for DB start/stop
3. All `php artisan` commands run in terminal as normal

Date locked: 2026-04-09

---

### D-002 — Claim token format
**Blocks:** CustomerActivation module
**Status:** 🟢 LOCKED — Short alphanumeric `HLP` prefix + 5 random uppercase chars (e.g. `HLP4X9K2`)

- Human-readable: cashier fallback readable ✓
- DB lookup: fast at this scale ✓
- eWards DB: local DB now; FK swap to eWards DB when integration contracts arrive
- Collision strategy: retry up to 5 times; fallback to 8-char suffix (extremely rare)

Date locked: 2026-04-10

---

### D-003 — Rule snapshot storage format
**Blocks:** Execution module, Ledger module
**Status:** 🟢 LOCKED — Versioned table (`partnership_rule_versions`) + FK on `partner_redemptions`

- `partnership_rule_versions` table: one row per unique rule configuration
- `partner_redemptions.rule_version_id`: FK to the version in effect at redemption time
- `partner_redemptions.rule_snapshot` JSON column: retained as fallback / human-readable copy
- Rationale: at 100M+ redemptions, storing a 2KB JSON blob per row = ~200GB wasted.
  The versioned table stores each rule set once regardless of how many redemptions use it.
- Phase 2: drop the inline `rule_snapshot` column once all consumers read from the versioned table.

Date locked: 2026-04-10

---

### D-004 — Cap exhaustion race condition strategy
**Blocks:** RulesEngine module, Execution module
**Status:** 🟢 LOCKED — `SELECT FOR UPDATE` on `partnership_cap_counters` row

When two cashiers hit the same cap simultaneously, the DB locks the counter row for ~2ms so only one can update it at a time. Second cashier sees the correct (updated) remaining amount.
Redis upgrade option preserved for when eWards infra spec arrives.

Date locked: 2026-04-09

---

### D-005 — QR code type: static per-outlet vs dynamic per-claim
**Blocks:** CustomerActivation module
**Status:** 🟢 LOCKED — Static per-outlet QR

- URL format: `{base}/claim/{partnership_uuid}?from={outlet_id}`
- QR generated client-side in PartnershipDetailView.vue using `qrcode` npm package
- One QR per participating outlet, per partnership
- Printed once; token is issued AFTER customer submits their phone via WhatsApp

Date locked: 2026-04-10

---

### D-006 — WhatsApp gateway provider
**Blocks:** CustomerActivation module
**Status:** 🟢 LOCKED — MSG91 (Indian vendor) for standalone testing

- Local standalone build: MSG91 SDK wired with `WHATSAPP_DRIVER=mock` env fallback
- When `WHATSAPP_DRIVER=msg91`: sends via MSG91 WhatsApp Business API
- When `WHATSAPP_DRIVER=mock`: logs to `storage/logs/laravel.log` (default for local dev)
- eWards migration: swap provider to eWards gateway when spec received — interface unchanged
- MSG91 credentials needed: `MSG91_AUTH_KEY`, `MSG91_SENDER_ID` in `.env`

Date locked: 2026-04-10

---

### D-007 — Inactivity threshold for "reactivated customer"
**Blocks:** RulesEngine customer-type logic
**Status:** 🟢 LOCKED — Manual per partnership, no system default enforcement

- `partnership_rules.inactivity_days` column exists (DB default: 90)
- Merchant sets this per partnership via the rules editor
- No automatic system enforcement of a platform default
- Special case: if an outlet/merchant leaves the eWards ecosystem, all their partnerships
  are auto-closed and greyed out (see E-001 below)

Date locked: 2026-04-10

---

### D-008 — Partnership scope resolution model
**Blocks:** Partnership module, RulesEngine module
**Status:** 🟢 LOCKED — Two-tier scope model

**Tier 1 — Outlet-level partnership**
A national brand (e.g. coffee chain) partners with a local gym in Bandra.
Only their Bandra outlet participates — not their outlets in Delhi or Pune.
Different outlet → different partnership record, potentially different merchant partner.

**Tier 2 — Brand-level partnership**
A national brand sets up one partnership that applies across ALL their outlets nationally.
The partner brand may also opt in all their outlets, or select specific ones.

**How this is modelled in `partnership_participants`:**
- `outlet_id = NULL` → this merchant's ALL outlets are in scope
- `outlet_id = specific ID` → only that outlet is in scope
- A brand-level partnership has `outlet_id = NULL` for the national brand's participant row
- You can mix: brand A all outlets (`outlet_id=NULL`) + brand B only selected outlets (multiple rows with specific `outlet_id`s)

**Scope resolution at redemption time:**
RulesEngine checks: does a LIVE partnership exist where
  - this outlet's merchant_id matches a participant AND
  - participant.outlet_id = this outlet_id OR participant.outlet_id IS NULL

Date locked: 2026-04-09

---

### D-009 — Approval flow trigger
**Blocks:** Execution module
**Status:** 🟢 LOCKED — No approval required by default

- `partnership_terms.approval_mode` defaults to `1` (auto — no approval required)
- Manager approval (`approval_mode = 2`) and OTP approval (`approval_mode = 3`) are
  available as merchant-configurable options per partnership
- Default for new partnerships: `approval_mode = 1` (auto)
- Infrastructure (ApprovalService, approval_code flow) already built and tested

Date locked: 2026-04-10

---

### D-010 — Frontend routing: SPA vs SSR
**Blocks:** Frontend scaffold
**Status:** 🟢 LOCKED — Vue 3 SPA (Vite)

- No SEO requirement for merchant dashboard
- Simpler build, faster iteration
- Nuxt 3 / SSR deferred unless a public-facing marketing page is added

Date locked: 2026-04-10

---

## eWards Integration Dependencies

---

### E-001 — Ecosystem exit: partnership auto-close + grey state
**Blocks:** eWards migration module (not blocking standalone build)
**Status:** 🟡 PENDING (eWards integration — mock locally)

**Requirement:**
When a merchant or outlet exits the eWards system (account suspended, brand offboarded,
login disabled), all their LIVE and PAUSED partnerships must:
1. Auto-close (status → TERMINATED or a new `ECOSYSTEM_INACTIVE` status)
2. Appear greyed out in the UI with label "Not in ecosystem"
3. Be non-actionable (no new issuances or redemptions)

**Local mock strategy:**
- Add a `ecosystem_active` boolean column to `merchants` table (default TRUE)
- Partnership module checks `ecosystem_active` on both participants at redemption time
- Admin can flip this flag locally to test the greyed-out UI state

**eWards integration:**
- Receive a webhook/event from eWards when a merchant is suspended/offboarded
- Handler sets `ecosystem_active = false` and triggers partnership auto-close job
- Timeline: implement when eWards integration contracts arrive

---

## 3-Layer Platform Architecture (Added Session 25)

### Layer 1: Super Admin (eWards / Platform Operator)
- **Access:** `/super-admin/*` — separate auth model, separate SPA
- **Controls:** Merchant onboarding, WA credit allocation, eWards integration approval, platform stats
- **Status:** BUILT

### Layer 2: Merchant Dashboard
- **Access:** `/login` → `/*` — Sanctum auth
- **Controls:** Partnerships, campaigns, analytics, discovery, networks, settings, QR codes
- **Status:** BUILT

### Layer 3: Customer-Facing Rewards Page
- **Access:** `/my-rewards` — phone + OTP authentication
- **Shows:** Loyalty point balances across all merchants, which outlets accept points, limitations per outlet
- **Auth:** Phone + OTP via WhatsApp. OTP credit paid by platform (SuperAdmin pool). Session: 24h.
- **Status:** 🟢 LOCKED — building Session 25

### D-011 — Customer OTP credit source
**Status:** 🟢 LOCKED
OTP WhatsApp messages for customer portal login are charged to a **platform-level credit pool**, not individual merchant credits. SuperAdmin manages this separately.

---

## Ecosystem Scenario Matrix (Added Session 25)

### Partnership State × Merchant State — All Combinations

Each partnership involves **Merchant A** (proposer) and **Merchant B** (acceptor).
Each merchant has `ecosystem_active` = true/false.

#### Legend
- **ecosystem_active=true** → merchant is operating normally
- **ecosystem_active=false** → merchant left eWards ecosystem (suspended/offboarded)
- **cap_exhausted** → monthly/daily/lifetime cap has been fully used

---

### Scenario 1: Both Active — Normal Flow
| Merchant A | Merchant B | Partnership State | What Happens |
|---|---|---|---|
| active | active | LIVE | Normal operation. Claims issued, redemptions processed, analytics tracked. |
| active | active | PAUSED | No new claims or redemptions. Both can resume. |
| active | active | NEGOTIATING | Both can edit terms, accept, or reject. |
| active | active | AGREED | Either side can trigger Go Live. |

### Scenario 2: One Inactive
| Merchant A | Merchant B | Partnership State | What Happens | Implemented? |
|---|---|---|---|---|
| **inactive** | active | LIVE | Partnership → ECOSYSTEM_INACTIVE. No claims, no redemptions. Greyed in UI. | ✓ Session 25 |
| **inactive** | active | PAUSED | Partnership → ECOSYSTEM_INACTIVE. Cannot resume. | ✓ Session 25 |
| **inactive** | active | NEGOTIATING | Partnership → ECOSYSTEM_INACTIVE. Cannot continue negotiation. | ✓ Session 25 |
| **inactive** | active | REQUESTED | Partnership → ECOSYSTEM_INACTIVE. Cannot accept. | ✓ Session 25 |
| **inactive** | active | SUGGESTED | Partnership → ECOSYSTEM_INACTIVE. | ✓ Session 25 |
| active | **inactive** | Any active state | Same as above — listener fires on EITHER participant going inactive. | ✓ |

### Scenario 3: Both Inactive
| Merchant A | Merchant B | Partnership State | What Happens |
|---|---|---|---|
| inactive | inactive | Any | Partnership → ECOSYSTEM_INACTIVE (already closed by first exit). No further action. |

### Scenario 4: Reactivation
| Before | After | What Happens | Implemented? |
|---|---|---|---|
| One merchant inactive | Merchant reactivated via webhook | `ecosystem_active` → true. Partnerships stay ECOSYSTEM_INACTIVE. **Manual resume required.** | ✓ Webhook exists |
| Both inactive → one reactivated | — | No change until both are active AND admin manually resumes. | ✓ By design |

### Scenario 5: Cap Exhaustion
| Merchant A | Merchant B | Cap State | What Happens | Implemented? |
|---|---|---|---|---|
| active | active | Monthly cap hit | `PartnershipCapExhausted` event → `AutoPauseOnCapExhausted` → partnership auto-pauses | ✓ |
| active | active | Daily cap hit | Redemptions denied for the day. Partnership stays LIVE. Next day resets. | ✓ (RulesEngine) |
| active | active | Lifetime cap hit | Redemptions denied permanently until cap is raised. Partnership stays LIVE. | ✓ (RulesEngine) |
| active | active | Both sides' caps hit | Same as monthly — first cap triggers auto-pause. | ✓ |
| active | active | Cap resets (new month) | Partnership stays PAUSED. **No auto-resume.** Admin must manually resume. | ✓ By design |

### Scenario 6: Mixed States
| Situation | What Happens | Note |
|---|---|---|
| LIVE + one side pauses manually | Partnership → PAUSED. Either side can resume. | ✓ |
| Both sides pause simultaneously | Only one pause is recorded (first HTTP request wins). Partnership → PAUSED. | ✓ (idempotent) |
| PAUSED + ecosystem exit | Partnership → ECOSYSTEM_INACTIVE. | ✓ Session 25 |
| ECOSYSTEM_INACTIVE + cap reset | No change. Caps are irrelevant in inactive state. | ✓ |
| NEGOTIATING + terms edited by both | Last write wins per field. No conflict resolution. | Known limitation — acceptable for V1 |

---

### eWards Integration — Required Data Spec

For the Hyperlocal program to function through eWards integration, we need the following data flows:

#### 1. Member Data (Critical — blocks member targeting)
| Data Point | Direction | Purpose | Current Status |
|---|---|---|---|
| Customer phone (normalised) | eWards → Hyperlocal | Match customers across merchants | `SyncMembersFromIntegration` job exists, `getCustomers()` is stub |
| Customer external ID | eWards → Hyperlocal | Link our `members` to eWards records | `member_integrations` table exists |
| Transaction history (last visit date per merchant) | eWards → Hyperlocal | Classify as NEW / EXISTING / REACTIVATED | `CustomerClassifier` uses local heuristic — needs eWards data |
| Opt-in/opt-out status | eWards → Hyperlocal | Respect WhatsApp consent | `members.opted_out` exists locally |

#### 2. Loyalty Data (Critical — blocks point-based benefits)
| Data Point | Direction | Purpose | Current Status |
|---|---|---|---|
| Point balance per member per merchant | eWards → Hyperlocal | Show balance, check if enough to redeem | `getBalance()` stub in EwardsAdapter |
| Award points | Hyperlocal → eWards | Issue points when customer earns via partnership | `award()` stub in EwardsAdapter |
| Deduct points | Hyperlocal → eWards | Deduct points on redemption | `deduct()` stub in EwardsAdapter |
| Point valuation (₹ per point) | eWards config | Convert points ↔ rupees | `merchant_point_valuations` table exists locally |

#### 3. Webhook Events (Critical — blocks ecosystem sync)
| Event | Direction | Purpose | Current Status |
|---|---|---|---|
| merchant-exit | eWards → Hyperlocal | Suspend all partnerships when merchant leaves | ✓ Webhook handler built |
| merchant-reactivate | eWards → Hyperlocal | Re-enable merchant (manual partnership resume) | ✓ Webhook handler built |
| member-opt-out | eWards → Hyperlocal | Mark member as opted out | NOT BUILT — need event spec |
| points-adjusted | eWards → Hyperlocal | Sync balance after manual adjustment | NOT BUILT — need event spec |

#### 4. Analytics Data (Important — blocks accurate ROI)
| Data Point | Direction | Purpose | Current Status |
|---|---|---|---|
| Bill amount per transaction | POS → Hyperlocal (via eWards) | Calculate revenue from referred customers | Currently in `partner_redemptions.bill_amount` — entered by cashier at POS |
| Transaction count per customer per merchant | eWards → Hyperlocal | Retention tracking (30/60/90d) | `RetentionService` uses local `partner_redemptions` — works standalone |
| First-ever visit flag | eWards → Hyperlocal | Distinguish truly new vs already-in-eWards customers | `CustomerClassifier` uses local claims data — eWards history would be more accurate |
| Coupon redemption count (external) | eWards → Hyperlocal | Track if offer_announcement campaigns drive redemptions | Was PromoCode sync — REMOVED |

#### 5. Analytics — What We Calculate Locally (No eWards Needed)
| Metric | Source | Status |
|---|---|---|
| New customers via partnership | `partner_attributions` (first visit flag) | ✓ Built |
| 30/60/90-day retention | `RetentionService` cron job | ✓ Built |
| ROI per partnership | `RoiService` (revenue - benefit cost) | ✓ Built |
| Monthly trend (redemptions, new customers, revenue) | `AnalyticsController::summary` aggregation | ✓ Built |
| Per-partner breakdown | `RoiService::forPartnership` | ✓ Built |
| Campaigns sent / delivered | `campaign_sends` table counts | ✓ Built |
| Cap utilization % | `CapEnforcementService` reads `cap_counters` | ✓ Built |
| Reciprocity score | Customers sent vs received ratio | ✓ Built |

#### Summary: What We Need From eWards Before Going Live
1. **API spec for `getCustomers()`** — paginated member list with phone, external ID, last visit dates
2. **API spec for `getBalance() / award() / deduct()`** — point operations
3. **Webhook event spec** — member-opt-out, points-adjusted events
4. **Authentication method** — API key? OAuth? What headers?
5. **Rate limits** — how fast can we pull members? How many point ops/sec?
6. **Sandbox/staging environment** — for integration testing

---

## Event Trigger Infrastructure — Full Architecture Spec (Added Session 25)

### Vision

Shift the product from "hyperlocal partnership on eWards rails" to "partnership and offer infrastructure any merchant can plug into." Any merchant — whether on eWards, Shopify, WooCommerce, or a custom website — can fire an event that activates partnership offers, campaigns, or customer rewards.

### TAM Impact

Without this: reachable market = eWards merchants with POS integration.
With this: any merchant with a website, ecommerce store, booking system, or CRM can participate.

---

### Architecture: 4 Layers

```
┌─────────────────────────────────────────────────────────────┐
│  LAYER A — CONNECTORS (event sources)                        │
│                                                              │
│  Shopify   WooCommerce   eWards    Website URL   Server API  │
│  webhook   webhook       receipt   trigger GET   signed POST │
│                                                              │
│  Each connector normalizes source-specific payload           │
│  into the internal event format.                             │
└─────────────────────┬───────────────────────────────────────┘
                      │ Normalized event
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  LAYER B — EVENT ENGINE                                      │
│                                                              │
│  1. Receive + validate + deduplicate                         │
│  2. Resolve customer identity (phone/email/member_id)        │
│  3. Log raw + normalized event                               │
│  4. Queue for processing                                     │
└─────────────────────┬───────────────────────────────────────┘
                      │ Queued event
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  LAYER C — TRIGGER ENGINE                                    │
│                                                              │
│  1. Match event to configured triggers                       │
│  2. Check rules: merchant active? partnership live?          │
│     offer active? cap available? customer eligible?          │
│  3. Evaluate conditions (min amount, category, first buy)    │
│  4. Determine action(s)                                      │
└─────────────────────┬───────────────────────────────────────┘
                      │ Action(s) to execute
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  LAYER D — ACTION ENGINE                                     │
│                                                              │
│  • Issue partner offer token                                 │
│  • Expose offer on customer rewards page                     │
│  • Send WhatsApp / SMS / email                               │
│  • Push to campaign queue                                    │
│  • Create eligibility for later redemption                   │
│  • Update ledger / attribution state                         │
│  • Fire webhook to merchant                                  │
└─────────────────────────────────────────────────────────────┘
```

---

### Normalized Event Model

Every incoming event (from any source) is converted to this internal format:

```
{
  event_id:          "evt_abc123",           // unique, for dedup
  merchant_id:       42,                     // resolved from API key
  event_type:        "transaction_completed", // normalized type
  event_time:        "2026-04-13T14:30:00Z",
  customer_ref: {
    phone:           "919900001111",         // primary identifier
    email:           "rahul@example.com",    // secondary
    external_id:     "cust_xyz",             // source system ID
    member_id:       null                    // resolved internally
  },
  amount:            850.00,                 // transaction value
  currency:          "INR",
  order_id:          "ORD-1234",             // source order reference
  category:          "beverages",            // optional product category
  channel:           "website",              // where the event happened
  source:            "shopify",              // which connector sent it
  metadata: {                                // flexible extra data
    product_name:    "Cold Brew",
    first_purchase:  true
  }
}
```

### Standard Event Types

| Event Type | Description | Common Sources |
|-----------|-------------|---------------|
| `transaction_completed` | Customer paid a bill | POS, eWards receipt, Shopify order |
| `first_purchase` | Customer's first-ever transaction | Shopify, website |
| `order_above_threshold` | Transaction exceeds a configured amount | Any |
| `booking_confirmed` | Service booking completed | Booking platforms |
| `order_delivered` | Delivery confirmed | Ecommerce |
| `membership_activated` | Customer joined a membership/plan | Gym, salon, club |
| `milestone_reached` | Customer hit a loyalty milestone | CRM, loyalty system |
| `category_purchased` | Customer bought from a specific category | POS, ecommerce |

---

### Identity Resolution Priority

When resolving which `member` an event belongs to:

1. `member_id` — if event includes our internal member ID (from previous interaction)
2. `phone` — normalised via `MemberService::normalise()` (add `91` prefix for Indian numbers)
3. `email` — fallback if phone not available
4. `external_id` — source system's customer ID, mapped via `member_integrations`
5. Create new member if no match found (auto-registration)

---

### Database Tables (Phase 1)

**`event_sources`** — Merchant's configured event connections
```
id, uuid, merchant_id, name, source_type (shopify|woocommerce|ewrds|website|api),
api_key (for signed requests), webhook_secret,
status (active|paused|disconnected), test_mode (boolean),
created_by, timestamps
```

**`event_triggers`** — Rules: "when X event happens, do Y"
```
id, uuid, merchant_id, event_source_id (FK),
event_type, condition_json (min_amount, category, first_purchase, etc.),
action_type (issue_offer|send_campaign|make_eligible|send_whatsapp),
action_config_json (offer_id, campaign_id, template, etc.),
partnership_id (FK nullable), is_active (boolean),
created_by, timestamps
```

**`event_log`** — Every received event (raw + processed)
```
id, event_source_id, merchant_id, idempotency_key (unique),
raw_payload (JSON), normalized_payload (JSON),
processing_status (received|processing|completed|failed|duplicate),
action_outcome (JSON nullable), error_reason (text nullable),
received_at, processed_at, timestamps
```

---

### Connector Specifications

#### A. Frontend Trigger URL (Simplest)

Merchant places a pixel/script on their website's thank-you page:

```html
<!-- After order confirmation -->
<img src="https://hyperlocal.app/api/events/pixel/{merchantKey}?event=transaction_completed&amount=850&phone=9900001111" />
```

Or a JS snippet:
```javascript
<script>
  fetch('https://hyperlocal.app/api/events/trigger', {
    method: 'POST',
    headers: { 'X-Merchant-Key': 'mk_abc123' },
    body: JSON.stringify({
      event: 'transaction_completed',
      amount: 850,
      phone: '9900001111',
      order_id: 'ORD-1234'
    })
  });
</script>
```

#### B. Server-to-Server Signed API

```
POST /api/events/ingest
X-Merchant-Key: mk_abc123
X-Signature: sha256(timestamp + body + secret)
X-Timestamp: 1681000000

{
  "event_type": "transaction_completed",
  "amount": 850,
  "customer": { "phone": "9900001111", "email": "rahul@example.com" },
  "order_id": "ORD-1234",
  "metadata": { "category": "beverages" }
}
```

Response:
```json
{ "event_id": "evt_abc123", "status": "accepted", "actions_queued": 1 }
```

#### C. Shopify Webhook

Register for `orders/create` webhook:
```json
{
  "topic": "orders/create",
  "address": "https://hyperlocal.app/api/connectors/shopify/{merchantKey}/orders",
  "format": "json"
}
```

Connector normalizes Shopify order payload → internal event format.

#### D. eWards Native

Already exists via the existing webhook system (`POST /webhooks/ecosystem/*`). Extend to support transaction events from eWards POS.

---

### Merchant Setup UX Flow

```
Step 1: Choose source
  → Shopify | WooCommerce | Website | API | eWards

Step 2: Connect
  → Shopify: install app / enter API key
  → Website: copy trigger URL / pixel code
  → API: get merchant key + secret

Step 3: Choose event
  → transaction_completed | first_purchase | order_above_X | booking_confirmed

Step 4: Set conditions
  → min amount | category | customer type | time window

Step 5: Choose action
  → issue partner offer | unlock promo code | send WhatsApp | make eligible

Step 6: Link to partnership/offer
  → select which partnership or offer this trigger activates

Step 7: Test
  → "Send test event" button → see simulated outcome

Step 8: Go live
  → Active / Paused toggle
```

---

### Security Requirements

1. **API Key per merchant** — generated on connection, rotatable
2. **Request signing** — `HMAC-SHA256(timestamp + body, secret)`, 5-minute timestamp tolerance
3. **Idempotency** — `idempotency_key` (order_id + event_type + merchant_id) prevents duplicates
4. **Rate limiting** — 100 events/minute per merchant, burst allowance
5. **Test mode** — events logged but no actions executed, visible in dashboard

---

### Integration with Existing Modules

| Existing Module | How Event Engine Connects |
|----------------|-------------------------|
| PartnerOffers | Event triggers offer eligibility → offer appears on customer's rewards/bill page |
| Campaign | Event triggers campaign send → WhatsApp message dispatched |
| CustomerActivation | Event replaces QR scan → claim token auto-issued |
| RulesEngine | Event-triggered redemptions go through same cap/eligibility checks |
| Analytics | Events create attribution records → feed ROI calculations |
| Ledger | Event-triggered benefits create ledger entries |
| CustomerPortal | Events make offers visible on `/my-rewards` page |

---

### Build Phases

| Phase | Scope | Effort |
|-------|-------|--------|
| **Phase 1** | Event model + ingestion API + logs + trigger URL + dedup | 2-3 days |
| **Phase 2** | Trigger rules engine + action execution + merchant config UI | 3-4 days |
| **Phase 3** | Shopify + WooCommerce prebuilt connectors | 2-3 days |
| **Phase 4** | Campaign/WhatsApp action integration + advanced conditions | 2-3 days |
| **Phase 5** | Analytics dashboard for events + conversion tracking | 1-2 days |

### Status: ✓ BUILT (Session 25) — All phases implemented.

---

## Scaling Strategy — 10,000+ Concurrent Users

### Current Architecture (Standalone Dev)
- `php artisan serve` — single-threaded, 1 request at a time
- MySQL on local DBngin — single instance
- Cache: database driver
- Queue: database driver (sync in dev)
- No CDN, no load balancer

### Production Architecture (10K+ Concurrent)

#### Tier 1: Immediate (Before First Real Traffic)

| Component | Current | Production | Why |
|-----------|---------|------------|-----|
| Web server | `php artisan serve` | Nginx + PHP-FPM (16 workers) | Handle concurrent requests |
| Cache | Database | Redis | OTP storage, bill offers cache, rate limiting |
| Queue | Database (sync) | Redis + Horizon (3 workers per queue) | Campaign sends, event processing, health scores |
| Sessions | Database | Redis | Faster auth checks |

Queues to configure:
- `default` — general jobs
- `campaigns` — WhatsApp message dispatch (rate-limited)
- `events` — event trigger processing (high throughput)

#### Tier 2: Growth (1,000+ Merchants, 100K+ Monthly Events)

| Component | Action | Why |
|-----------|--------|-----|
| Read replicas | MySQL read replica for analytics queries | Dashboard + analytics don't need real-time writes |
| CDN | CloudFront for bill offers page + brand profiles | Public pages get high traffic from bill links |
| Event log partitioning | Partition `event_log` by month | Table grows fastest — partition prevents slow queries |
| Bill offers cache | Redis cache per merchant (60s TTL) | `BillOffersService::getOffersForMerchant()` runs a complex JOIN |
| Rate limiting | Redis-backed throttle (not database) | API rate limits need atomic counters |

#### Tier 3: Scale (10,000+ Merchants, 1M+ Monthly Events)

| Component | Action | Why |
|-----------|--------|-----|
| Cap counters | Move `SELECT FOR UPDATE` to Redis INCR | Pessimistic DB lock is fine at <1K TPS, Redis handles >10K TPS |
| Event ingestion | SQS/SNS for async event processing | Decouple ingestion from processing |
| Analytics | Pre-computed materialized views or Elasticsearch | Real-time aggregation won't scale past 100M rows |
| WhatsApp sends | Dedicated queue + MSG91 bulk API | Avoid per-message API calls |
| Horizontal scaling | Multiple PHP-FPM containers behind ALB | Stateless app, Redis for shared state |

### Queries That Need Attention at Scale

| Query | Location | Current | At Scale |
|-------|----------|---------|----------|
| Analytics summary | `AnalyticsController::summary` | JOINs across 5 tables | Pre-compute daily, cache |
| Bill offers resolution | `BillOffersService::getOffersForMerchant` | 2 subqueries + DISTINCT | Cache per merchant (60s) |
| Discovery fit scoring | `FitScoringService` | Full table scan on merchants | Nightly batch (already implemented) |
| Event trigger matching | `TriggerEngineService::match` | Query per event | Cache active triggers per merchant |
| Health scores | `PartnershipHealthService::computeAll` | N+1 queries per partnership | Batch with raw SQL |

### Database Indexes Already in Place
- All `merchant_id` + `status` combinations indexed
- `partnership_cap_counters` has compound unique on (merchant, partnership, outlet, year, month)
- `event_log.idempotency_key` unique index for dedup
- `partner_offers` indexed on (merchant_id, status, deleted_at) and (expiry_date, status)
- `partner_redemptions` has idempotency unique on (merchant_id, transaction_id)

### Cost Estimate at Scale

| Scale | Infra | Estimated Monthly Cost |
|-------|-------|----------------------|
| 100 merchants, 10K events/mo | Single EC2 t3.medium + RDS t3.small + ElastiCache t3.micro | ~$80/mo |
| 1,000 merchants, 100K events/mo | 2x EC2 t3.large + RDS r6g.large + ElastiCache r6g.large | ~$400/mo |
| 10,000 merchants, 1M events/mo | ALB + 4x ECS Fargate + RDS r6g.xlarge + ElastiCache cluster | ~$1,500/mo |

### Member Data at Scale (eWards Lesson Applied)

eWards has seen millions of customers per merchant. The `members` table is the most critical scaling concern.

**Current design:**
- `members` table: phone (unique), name, email, whatsapp_opt_in, last_seen_at
- `member_integrations`: member_id + provider + external_id (links to eWards/Shopify customer IDs)
- `member_loyalty_balances`: member_id + merchant_id + balance (denormalized cache)
- `partner_claims.member_id` + `partner_redemptions.member_id` — FK to members

**Problem at 10M+ members:**
- Phone-based lookups (`WHERE phone = ?`) are fast with index — no issue
- `SyncMembersFromIntegration` job pulls pages of 200 — will take hours for millions
- `member_loyalty_balances` grows as members × merchants — could be 100M+ rows
- Campaign segment queries (`partner_claims WHERE merchant_id = X`) scan millions of claims

**Scaling plan for member data:**

| Concern | Solution | When |
|---------|----------|------|
| `members` table > 10M rows | Partition by phone prefix (first 2 digits) | Before eWards sync goes live |
| `SyncMembersFromIntegration` hours-long | Parallel workers (chunk by page, dispatch multiple jobs) | Before eWards sync goes live |
| `member_loyalty_balances` > 100M rows | Partition by merchant_id range | At 1,000+ merchants |
| Campaign segment query slow | Pre-compute segment membership in a `campaign_segments` table | At 100K+ members per merchant |
| `partner_claims` > 50M rows | Partition by `created_at` (monthly) | At 10M+ claims |
| `partner_redemptions` > 50M rows | Partition by `created_at` (monthly) | At 10M+ redemptions |
| Identity resolution at ingestion | Redis cache for phone → member_id mapping (TTL 24h) | Before event trigger volume > 10K/day |
| Duplicate member detection | Normalize phone before insert (already done with `MemberService::normalise`) | Already implemented |

**Critical: the `SyncMembersFromIntegration` job must be redesigned before eWards goes live.**

Current design: single job, sequential pages of 200 → at 1M members = 5,000 pages = hours.

Better design:
1. First call: get total count from eWards API
2. Dispatch N parallel jobs, each handling a page range (e.g., pages 1-100, 101-200, etc.)
3. Each job runs independently with its own retry logic
4. Progress tracked in a `sync_jobs` table
5. Merchant dashboard shows sync progress bar

This is a Phase 2 concern — the architecture supports it but the current single-job implementation needs splitting.

### Status: Architecture documented. No infrastructure changes needed until first 100 merchants.

---

## Decisions Already LOCKED

| ID | Decision | Date Locked |
|----|----------|-------------|
| D-L001 | Stack: Laravel 10 + TypeScript + Vue 3 + MySQL | 2026-04-09 |
| D-L002 | Build standalone locally first, migrate to eWards later | 2026-04-09 |
| D-L003 | eWards integration contracts deferred | 2026-04-09 |
| D-L004 | Domain-grouped module structure (`app/Modules/`) | 2026-04-09 |
| D-L005 | FLOWCHART.md updated every session | 2026-04-09 |
| D-L006 | Debugging always full-flow, never patchwork | 2026-04-09 |
| D-L007 | Every module has a README with explicit boundaries | 2026-04-09 |
| D-001 | Laravel Herd + DBngin for local MySQL (terminal for artisan) | 2026-04-09 |
| D-002 | Short alphanumeric token `HLP` + 5 chars; local DB lookup | 2026-04-10 |
| D-003 | Versioned rule table (`partnership_rule_versions`) + FK on redemptions | 2026-04-10 |
| D-004 | SELECT FOR UPDATE on cap_counters for race condition safety | 2026-04-09 |
| D-005 | Static per-outlet QR; URL = `/claim/{uuid}?from={outlet_id}` | 2026-04-10 |
| D-006 | MSG91 WhatsApp for testing; mock fallback via `WHATSAPP_DRIVER` env | 2026-04-10 |
| D-007 | Manual inactivity threshold per partnership; no platform default | 2026-04-10 |
| D-008 | Two-tier scope: outlet_id=NULL means brand-wide, specific outlet_id means outlet-level | 2026-04-09 |
| D-009 | No approval required by default (approval_mode=1); manager/OTP available | 2026-04-10 |
| D-010 | Vue 3 SPA (Vite); no SSR | 2026-04-10 |
