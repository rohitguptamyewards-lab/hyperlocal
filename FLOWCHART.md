# Hyperlocal Partnership Network — Full Flow Chart

> **RULE:** This file must be updated every session.
> Every connection between modules must be represented here.
> If a line is missing, there is a hole in the design.
> Debugging always traces this full flow — never as a patch on one layer.

---

## Last Updated: 2026-04-13 (Session 25 — Full audit, CustomerPortal, PartnerOffers, brand rename, video)

---

## 1. End-to-End System Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        HYPERLOCAL PARTNERSHIP NETWORK                       │
│                         Full Flow — All Connections                         │
└─────────────────────────────────────────────────────────────────────────────┘

╔══════════════════╗
║  SUPER ADMIN     ║  ← Separate SPA at /super-admin/*
║  MODULE          ║    Own auth guard, own axios instance, own localStorage key
║                  ║    Manages: merchants, WA credits, eWards integration requests
╚══════╤═══════════╝
       │ SuperAdmin pre-loads WhatsApp credits for merchant
       │ SuperAdmin approves/rejects eWards integration requests
       ▼
╔══════════════════╗
║   MERCHANT       ║  ← Merchant registers / invited to ecosystem
║   ONBOARDING     ║    eWards integration optional (request → SA approves → SyncMembers job)
╚══════╤═══════════╝
       │
       ▼
╔══════════════════╗
║   DISCOVERY      ║  ← System auto-suggests 3–5 partners
║   MODULE         ║    based on category, location, cluster density
╚══════╤═══════════╝
       │ Merchant selects a partner or searches manually
       ▼
╔══════════════════╗
║   PARTNERSHIP    ║  ← Creates partnership proposal
║   MODULE         ║    Status: SUGGESTED → REQUESTED → NEGOTIATING
╚══════╤═══════════╝    → AGREED → LIVE → PAUSED → EXPIRED
       │
       ├─── Emits: partnership.requested → Ops workflow + merchant inbox
       ├─── Emits: partnership.accepted  → Config workflow
       │
       ▼
╔══════════════════╗
║   AGREEMENT &    ║  ← Legal/commercial acceptance, outlet selection,
║   ONBOARDING     ║    go-live checklist, staff contacts
╚══════╤═══════════╝
       │ Agreement signed, outlets confirmed
       ▼
╔══════════════════╗
║   RULES ENGINE   ║  ← Merchant configures caps:
║   MODULE         ║    per-bill cap, %, monthly cap, outlet cap,
╚══════╤═══════════╝    customer-type rules, blackout, stacking rules
       │ Rules saved with version snapshot
       │ Emits: partnership.live → QR issuance + staff training trigger
       ▼
╔══════════════════════════════════════════════════════════════╗
║                  CUSTOMER ACTIVATION LAYER                   ║
║                                                              ║
║  Customer scans QR at outlet                                 ║
║       │                                                      ║
║       ▼                                                      ║
║  WhatsApp opens (prefilled intent)                           ║
║       │                                                      ║
║       ▼                                                      ║
║  System resolves: source outlet → eligible partner offers    ║
║       │                                                      ║
║       ▼                                                      ║
║  Customer taps claim → token generated                       ║
║       │                                                      ║
║       ├── Emits: partner.claim.created → WhatsApp msg + analytics
║       │                                                      ║
║       ▼                                                      ║
║  Claim token / dynamic QR issued with validity window        ║
╚══════════════════════════════════╤═══════════════════════════╝
                                   │ Customer walks to partner outlet
                                   ▼
╔══════════════════════════════════════════════════════════════╗
║                    EXECUTION LAYER (POS API)                 ║
║                                                              ║
║  POS terminal looks up claim code / scans QR                 ║
║       │                                                      ║
║       ▼                                                      ║
║  Rules Engine: evaluate eligibility                          ║
║    ├── Is token valid and not expired?                       ║
║    ├── Is monthly/partner/outlet cap not exhausted?          ║
║    ├── Is customer NEW / EXISTING / REACTIVATED?             ║
║    ├── Does bill meet min threshold?                         ║
║    ├── Is blackout / time rule satisfied?                    ║
║    └── Is stacking allowed with other active offers?         ║
║       │                                                      ║
║       ├── FAIL → Show reason code + fallback help prompt     ║
║       │                                                      ║
║       ▼ PASS                                                 ║
║  Approval flow (if configured) → manager / OTP               ║
║       │                                                      ║
║       ▼                                                      ║
║  Redemption executed — rule snapshot stored                  ║
║       │                                                      ║
║       ├── Emits: partner.redemption.executed                 ║
║       ├── Emits: customer.first_visit_via_partnership (if new)
║       └── Emits: partnership.cap.exhausted (if cap hit)      ║
╚══════════════════════════════════╤═══════════════════════════╝
                                   │
                     ┌─────────────┴─────────────┐
                     ▼                           ▼
          ╔══════════════════╗       ╔══════════════════╗
          ║  LEDGER MODULE   ║       ║  ANALYTICS /     ║
          ║                  ║       ║  ATTRIBUTION     ║
          ║  Virtual ledger  ║       ║                  ║
          ║  entry created   ║       ║  First-visit flag ║
          ║  (debit/credit   ║       ║  Customer typed  ║
          ║   per period)    ║       ║  30/60/90-day    ║
          ║                  ║       ║  retention jobs  ║
          ║  Monthly stmt    ║       ║  ROI calculation ║
          ║  generated       ║       ║  Reciprocity     ║
          ╚══════════════════╝       ╚══════╤═══════════╝
                     │                     │
                     └──────────┬──────────┘
                                ▼
                   ╔══════════════════════╗
                   ║  MERCHANT DASHBOARD  ║
                   ║                      ║
                   ║  Revenue             ║
                   ║  New customers       ║
                   ║  90-day retained     ║
                   ║  Cap utilization     ║
                   ║  Reciprocity score   ║
                   ║  Renewal signal      ║
                   ╚══════════════════════╝

           PARALLEL FLOWS:
           ──────────────
           partnership.cap.exhausted ──→ RULES ENGINE auto-pause check
                                     ──→ Merchant alert (notification)

           partnership.dormant       ──→ ENABLEMENT MODULE
                                         (staff re-activation prompt)

           partnership.statement.generated ──→ Merchant finance view
                                            ──→ Renewal workflow trigger
```

---

## 2. Campaign Flow (Updated Session 25)

```
╔══════════════════╗
║  CAMPAIGN        ║  ← Merchant creates campaign with:
║  MODULE          ║    - Template (coupon_issued / earn_reminder / etc.)
║                  ║    - Variable values (code = promo code from registry)
║                  ║    - Target segment:
║                  ║        source=own  → members with claims at this merchant
║                  ║        source=partner → members with claims at partner's side
║                  ║    - Audience preview: POST /campaigns/segment-preview (read-only)
╚══════╤═══════════╝
       │
       ├── Check WA credit balance BEFORE submit
       │     GET /merchant/whatsapp-balance
       │     WHATSAPP_CREDIT_ENFORCEMENT=false (default — tracks but never blocks)
       │     WHATSAPP_CREDIT_ENFORCEMENT=true  → throws InsufficientWhatsAppCreditsException
       │
       ▼
╔══════════════════╗
║  WHATSAPP        ║  ← WhatsAppCreditService: SELECT FOR UPDATE (pessimistic lock)
║  CREDIT          ║    Immutable ledger + denormalized balance cache
║  SYSTEM          ║    Credits pre-loaded by SuperAdmin
║                  ║    1 credit = 1 WhatsApp message sent
╚══════╤═══════════╝
       │ Campaign dispatches CampaignMessageJob per recipient
       ▼
╔══════════════════╗
║  WHATSAPP        ║  ← Driver: mock (local dev) | msg91 (India gateway)
║  NOTIFIER        ║    Config: WHATSAPP_DRIVER, MSG91_AUTH_KEY, etc.
╚══════════════════╝

   AUDIENCE SEGMENT SOURCES:
   ──────────────────────────
   source=own
     → Members who have claims at THIS merchant
     → Optional filter: last_seen_days
     → Query: member_claims WHERE merchant_id = {merchant}

   source=partner
     → Members who have claims at the PARTNER'S side of a specific partnership
     → partnership_id = UUID of the selected live partnership
     → Query: member_claims WHERE merchant_id = {partner_merchant}
              JOIN partnerships WHERE uuid = {partnership_id}
     → Used for: coupon_issued, partnership_welcome, partnership_earn templates
```

---

## 3. Network Module Flow (Added Session 21)

```
╔══════════════════╗
║  NETWORK         ║  ← Merchant creates a network (becomes owner)
║  MODULE          ║    hyperlocal_networks + network_memberships
║                  ║    Owner auto-added as first member on create
╚══════╤═══════════╝
       │ Owner sends invite
       ▼
╔══════════════════╗
║  NETWORK         ║  ← network_invitations: token (64-char random), 72h validity
║  INVITATION      ║    Invite channels: link | whatsapp | email
║                  ║    Shareable join URL: /networks/join/{token}
╚══════╤═══════════╝
       │ Invited merchant opens join URL
       ▼
╔══════════════════╗
║  NETWORK JOIN    ║  ← POST /merchant/networks/join/{token}
║                  ║    Validates: not expired, not already member
║                  ║    Creates NetworkMembership, marks invite accepted
╚══════════════════╝

   NOTE: Networks are informational groupings only.
   Loyalty/campaign calculations are isolated to individual partnership_ids.
   No cross-network calculation logic. Networks do NOT affect rules engine.
```

---

## 4. SuperAdmin + Integration Module Flow (Added Sessions 16–21)

```
╔══════════════════╗
║  SUPER ADMIN     ║  ← Separate authenticatable model (not merchants table)
║  AUTH            ║    POST /api/super-admin/auth/login
║                  ║    SuperAdminAuth middleware: instanceof SuperAdmin check
║                  ║    Completely isolated from merchant token space
╚══════╤═══════════╝
       │
       ├── Merchant management: GET /super-admin/merchants
       │     WA balance, eWards status, ecosystem active flag
       │     "Add Credits" → POST /super-admin/merchants/{id}/credits
       │
       ├── WA Credit allocation:
       │     WhatsAppCreditService::credit(merchantId, amount, note, adminId)
       │     Ledger entry + balance cache update (SELECT FOR UPDATE)
       │
       └── eWards Integration Requests:
             Merchant submits request → status: PENDING
             SA reviews → approve (config: api_key, base_url, brand_id)
                       → reject (reason)
             On approve: EwardsRequestService::approve()
               → sets integration config
               → dispatches SyncMembersFromIntegration job

╔══════════════════╗
║  WEBHOOK         ║  ← Receives ecosystem events from eWards
║  MODULE          ║    HMAC-SHA256 signing: "{timestamp}.{rawBody}"
║                  ║    Timestamp tolerance: 5 min
║                  ║    Nonce deduplication via cache (10 min TTL)
║                  ║    Key rotation: key_id field, multiple keys per source
╚══════╤═══════════╝
       │
       ├── POST /webhooks/ecosystem/merchant-exit
       │     → sets ecosystem_active=false
       │     → suspends ALL LIVE partnerships for that merchant
       │
       └── POST /webhooks/ecosystem/merchant-reactivate
             → sets ecosystem_active=true
             → does NOT auto-resume partnerships (manual action required)

╔══════════════════╗
║  MEMBER SYNC     ║  ← SyncMembersFromIntegration Job
║  JOB             ║    Triggered: on eWards approval + manual trigger
║                  ║    Paginated pull: getCustomers() 200/page
║                  ║    Per member: normalise phone → findOrCreateByPhone
║                  ║               → linkExternal(member, provider, external_id)
║                  ║    Per-member failures non-fatal (logged, skipped)
║                  ║    $tries=3, $timeout=600s
╚══════════════════╝
   NOTE: EwardsAdapter::getCustomers() is a stub — awaits eWards API spec.
```

---

## 5. Module Dependency Map

```
SuperAdmin ──────────────────────────────────────→ WhatsAppCreditService
SuperAdmin ──────────────────────────────────────→ EwardsRequestService
EwardsRequestService ────────────────────────────→ SyncMembersFromIntegration (Job)
SyncMembersFromIntegration ──────────────────────→ MemberService + LoyaltyAdapter
Webhook ─────────────────────────────────────────→ Partnership (suspend/reactivate)

Discovery ──────────────────────────────────────┐
                                                 ▼
Partnership ──→ RulesEngine ──→ Execution ──→ Ledger ──→ Analytics
     │               │               │
     ▼               ▼               ▼
Agreement      CustomerActivation  Enablement

Campaign ────────────────────────────────────────→ MemberService (segment query)
Campaign ────────────────────────────────────────→ WhatsAppCreditService
Campaign ────────────────────────────────────────→ WhatsAppNotifier (via Jobs)
Campaign ────────────────────────────────────────→ Partnership (partner segment source)

Network ─────────────────────────────────────────→ (informational only, no calc deps)

PartnerOffers ───────────────────────────────────→ Partnership (attachment via partnership_id)
PartnerOffers ───────────────────────────────────→ Network (publication via network_id)
PartnerOffers ───────────────────────────────────→ Merchants (bill_offers_enabled master switch)
```

| Upstream | Downstream | Integration Point |
|----------|-----------|-------------------|
| Partnership | RulesEngine | Partnership ID passed on rule config |
| Partnership | CustomerActivation | partnership.live event triggers QR |
| RulesEngine | Execution | Cap evaluation called per redemption |
| CustomerActivation | Execution | Claim token validated at POS via API |
| Execution | Ledger | Redemption record creates ledger entry |
| Execution | Analytics | Redemption creates attribution record |
| Ledger | Analytics | Period totals feed ROI calculation |
| Analytics | Dashboard (Frontend) | Aggregated metrics served via API |
| Execution | Enablement | Dormancy detection on usage gaps |
| Campaign | Partnership | Partner segment uses partnership UUID |
| Campaign | WhatsAppCreditService | Balance check + debit per message |
| SuperAdmin | WhatsAppCreditService | Credit allocation |
| Webhook | Partnership | Ecosystem exit → suspend all live partnerships |
| EwardsRequest | SyncMembersJob | Approval triggers member sync |

---

## 6. Event Bus Map

> All events are async. Consumers are listed. Adding a new consumer does NOT require modifying the emitter.
> ✓ = dispatched in code | ✗ = not dispatched | (no listener) = event fires but no consumer yet

| Event | Emitted By | Dispatched? | Consumed By |
|-------|-----------|-------------|-------------|
| `partnership.requested` | Partnership | ✗ NOT YET | Ops notifications, merchant inbox |
| `partnership.accepted` | Partnership | ✗ NOT YET | Config workflow |
| `partnership.live` | Partnership | ✗ NOT YET | CustomerActivation (QR), Enablement (training) |
| `partner.claim.created` | CustomerActivation | ✗ NOT YET | WhatsApp messaging, Analytics |
| `RedemptionExecuted` | Execution | ✓ RedemptionService | ✓ CreateLedgerEntryOnRedemption, RecordFirstVisitAttribution, UpdateLastUsedAtOnRedemption |
| `PartnershipCapExhausted` | Execution | ✓ RedemptionService | ✓ AutoPauseOnCapExhausted (auto-pauses partnership) |
| `partnership.dormant` | Enablement | ✗ NOT YET | Ops follow-up, staff re-activation |
| `partnership.statement.generated` | Ledger | ✗ NOT YET | Merchant finance view, Renewal trigger |
| `LowWhatsAppCreditEvent` | WhatsAppCreditService | ✓ WhatsAppCreditService deduct() | NotifyMerchantOnLowCredit (log + TODO email), NotifySuperAdminOnLowCredit (log; SA dashboard reads flag) |

---

## 7. Debugging Protocol

When a bug is reported (e.g. "redemption failed", "wrong discount applied", "cap not enforcing"):

1. **Start at the event** — which event in the Event Bus Map is the failure closest to?
2. **Trace upstream** — walk the Module Dependency Map backwards to find root cause
3. **Check rule snapshot** — every redemption stores the exact rules in force; compare with current rules
4. **Check state machine** — is the entity in the expected state? (partnership LIVE? claim not EXPIRED?)
5. **Never patch a single layer** — fix at root cause; re-verify the full chain above and below the fix

---

## 8. Built Modules Status

| Module | Backend | Frontend | Notes |
|--------|---------|----------|-------|
| Auth | ✓ | ✓ | register/login/logout/me |
| SuperAdmin | ✓ | ✓ | Separate SPA + auth; merchants, credits, eWards requests |
| Partnership | ✓ | ✓ | Full CRUD + state machine + all action buttons |
| RulesEngine | ✓ | N/A | 10-step eval, cap enforcement, cross-merchant token validation |
| CustomerActivation | ✓ | N/A | Claim issuance + WA mock (WhatsApp flow, no merchant UI needed) |
| Execution | ✓ | N/A (POS API) | UUID-based API, idempotency, cap exhaustion event — called by external POS |
| Ledger | ✓ | N/A | Double-entry; StatementService |
| Analytics | ✓ | ✓ | Attribution, 30/60/90d retention, ROI; RoiService feeds dashboard |
| Discovery | ✓ | ✓ | FitScoringService (category+geo+density); nightly batch; dismiss action |
| Enablement | ✓ | ✓ | PartnershipLive → rows; RedemptionExecuted → last_used_at; daily dormancy check |
| Campaign | ✓ | ✓ | Templates, WA credit check, promo code picker, partner segment, audience preview |
| PromoCode | ✗ | ✗ | REMOVED (Session 25) — external POS owns coupon lifecycle |
| WhatsAppCredit | ✓ | ✓ | Immutable ledger, pessimistic lock, ENFORCEMENT flag, shown in settings + campaign |
| Network | ✓ | ✓ | Create, invite (token), join, leave; informational grouping only |
| Webhook | ✓ | N/A | HMAC-SHA256, timestamp+nonce, ecosystem exit/reactivate |
| Member Sync | ✓ | N/A | SyncMembersFromIntegration Job; paginated getCustomers() stub |
| EwardsIntegration | ✓ | ✓ | Request flow; SA approve/reject; adapter stubs awaiting spec |
| CustomerPortal | ✓ | ✓ | Phone+OTP auth; rewards view with balances, partner outlets, limitations |
| PartnerOffers | ✓ | ✓ | Offer CRUD, partnership attachments, network publications, public bill offers page (3 display modes), impression/claim analytics |
| EventTriggers | ✓ | ✓ | Event ingestion (pixel/API/Shopify/WooCommerce), trigger rules engine, action executor, identity resolution, event log |
| Growth | ✓ | ✓ | Health scores, leaderboard, weekly digest, referral links, invite system, demand index, predictive matching, auto-pause/resume, seasonal templates, brand profiles, marketplace, sponsored placement |
| Migration | ✗ | ✗ | Blocked on eWards API spec |

---

## 9. Known Design Decisions & Constraints

| Decision | Status | Notes |
|----------|--------|-------|
| partnership.requested/accepted/live events NOT dispatched | PENDING | Blocked: no notification system yet |
| partner.claim.created NOT dispatched | PENDING | Blocked: no WA gateway |
| Execution event listeners: Ledger, Analytics, Enablement, AutoPause | BUILT | All four wired in AppServiceProvider |
| Approval flow handshake (`requires_approval=true` path) | PENDING | D-009: endpoint returns flag but no handshake UI |
| WHATSAPP_CREDIT_ENFORCEMENT=false default | LOCKED (D-006) | Safe until SA pre-loads credits; flip only after credits loaded |
| Network calculations isolated to partnership_id | LOCKED | Networks are groupings only; no cross-network rules |
| PromoCode module | REMOVED | Table dropped, module deleted — external POS owns coupon lifecycle |
| EwardsAdapter all methods are stubs | PENDING | Awaiting eWards API spec — see OPEN_DECISIONS.md E-001 |

---

## 10. Holes / Unconnected Nodes (Remaining)

| Item | Status | Blocker |
|------|--------|---------|
| eWards loyalty points pickup | PENDING | eWards API spec not received |
| eWards gift coupon redemption count | PENDING | eWards API spec not received |
| eWards member sync (getCustomers) | PENDING | eWards API spec not received |
| eWards balance/award/deduct | PENDING | eWards API spec not received |
| WhatsApp gateway integration | PENDING | Gateway provider not confirmed (msg91 wired but untested) |
| LowWhatsAppCreditEvent | BUILT | Fired from deduct(); two listeners; notification channel (email/WA) pending gateway |
| SA merchant detail page | BUILT | MerchantDetailView.vue — profile, WA credits + ledger, partnerships count, eWards status |
| Network membership on Partnership detail | NOT BUILT | Nice-to-have; informational only |
| Periodic promo code sync cron | REMOVED | PromoCode module deleted — no sync needed |
| Ledger entry on RedemptionExecuted | BUILT | CreateLedgerEntryOnRedemption wired in AppServiceProvider |
| Analytics attribution on FirstVisit | BUILT | RecordFirstVisitAttribution wired in AppServiceProvider |
| PartnershipCapExhausted consumer | BUILT | AutoPauseOnCapExhausted wired in AppServiceProvider |
| Frontend: outlet picker in create partnership modal | BUILT | PartnershipListView.vue outlet checkbox picker |
