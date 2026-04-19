# SESSION NOTES — Hyperlocal Partnership Network
Last updated: Session 27 (2026-04-13) — Video v17: VideoB viral (81s) + VideoC sales demo (120s)

---

## Session 27 Summary

### What Was Done — Video v15 Master

**v15:** "Your Neighbourhood Should Work For You" — scrapped. Tone still too presentation-like.
**v16:** "Let Your Neighbourhood Help You Grow" — scrapped. Lacked business clarity and persuasion structure.
**v17 goal:** Two videos for two distinct contexts — viral reel + structured sales demo.

**New component:**
- `src/video/src/components/HighlightScreenshotSlide.tsx` — Spotlight a screenshot region with a soft vignette gradient + subtle pulse animation. Props: `caption`, `headline`, `subtext`, `screenshotFile`, `highlight?: "top"|"bottom"|"left"|"right"|"center"`. Used on slides: how_find (right), how_propose (bottom), proof (top).

**Files modified:**
| File | Change |
|------|--------|
| `src/video/src/VideoB.tsx` | Rewritten as v17 viral — "Your Neighbourhood Works For You", 81s, 11 scenes |
| `src/video/src/VideoC.tsx` | Rewritten as v17 sales demo — "The Business Playbook", 120s, 17 scenes |
| `src/video/src/components/CTA.tsx` | URL → `hyperlocal.ewards.com`, headline → "Ready to see which brands near you are a match?" |
| `READING_SCRIPT.md` | VideoB + VideoC sections updated with v17 copy |

**Rendered:**
- `src/video/out/hyperlocal-v17-B.mp4` — 5.9 MB, ~81s (viral)
- `src/video/out/hyperlocal-v17-C.mp4` — 8.2 MB, ~120s (sales demo)

**VideoB v17 — Viral (81s):**
- Opens: "400 FitZone members. One café 200 metres away. 20 new customers last month. Zero ads."
- First screenshot at ~22s (M-05, highlight=right)
- 4 screenshots: M-05, M-03b, C-04-expanded, M-02
- Closes: "Your neighbourhood has been working against you. Now it works for you."

**VideoC v17 — Sales Demo (120s):**
- Opens: "Getting new customers is a grind." (problem-first)
- Scenes 1–6: all text — no screenshots before 35s
- 3 explicit use cases (M-03b, M-06, C-04-expanded) at scenes 7–9
- 6 screenshots total: M-03b, M-06, C-04, M-05, M-04, M-02
- Closes: "Your neighbourhood starts working for you."

**VideoA: unchanged** ("The Platform", 131s, investor/enterprise)

**Design decisions LOCKED:**
- VideoB = viral/social context, proof-first hook, 4 screenshots
- VideoC = sales demo context, structured playbook, 6 screenshots
- CTA URL: `hyperlocal.ewards.com` (placeholder — swap before final publish)

---

## Session 26 Summary

### What Was Done
Mobile responsiveness audit and fixes across all merchant dashboard views.

**Pre-existing (no work needed):**
- Customer activity history tab on rewards page — already built in Session 25
- `/my-rewards` link in WhatsApp campaign messages — already in all 5 templates via `{rewards_url}`
- Duplicate partnership guard — `checkDuplicatePartnership()` already in `PartnershipService::create()`

**Fixed this session — 13 files:**

| File | Fix |
|------|-----|
| `DashboardView.vue` | `grid-cols-3` → `grid-cols-1 sm:grid-cols-3` (customer flow section) |
| `PartnershipListView.vue` | `p-8` → `p-4 sm:p-8` |
| `CampaignView.vue` | `p-8` → `p-4 sm:p-8` + `grid-cols-4` → `grid-cols-2 sm:grid-cols-4` (send stats) |
| `FindPartnersView.vue` | `p-8` → `p-4 sm:p-8` |
| `MerchantSettingsView.vue` | `p-8` → `p-4 sm:p-8` |
| `PartnershipDetailView.vue` | `p-8` → `p-4 sm:p-8` |
| `PartnershipRedemptionsView.vue` | `p-8` → `p-4 sm:p-8` |
| `NetworkDetailView.vue` | `p-8` → `p-4 sm:p-8` |
| `NetworkListView.vue` | `p-8` → `p-4 sm:p-8` |
| `PartnershipLedgerView.vue` | `p-8` → `p-4 sm:p-8` |
| `PartnerOfferFormView.vue` | `grid-cols-3` → `grid-cols-1 sm:grid-cols-3` (display template picker) |

Views already correctly using `p-6 lg:p-8` (no change needed):
`DashboardView`, `PartnershipAnalyticsView`, `EventTriggerFormView`, `EventSourceListView`, `EventSourceSetupView`, `GrowthInsightsView`, `EventTriggerListView`, `PartnerOfferFormView`, `PartnerOfferDetailView`, `PartnerOfferListView`

### Decisions Made
All fixes are additive (Tailwind responsive prefix) — no behaviour change, purely layout.

---

## Next Session Starting Point
- All mobile responsiveness fixes applied
- All 48 E2E tests should still pass (no logic changed)
- Remaining open work: eWards integration (blocked on API spec from eWards team)

---

---

## Current Status
- **48/48 Playwright E2E tests passing** (zero failures, zero flakes)
- **3-layer platform complete**: SuperAdmin, Merchant Dashboard, Customer Portal
- All dead code removed, FLOWCHART corrected, "merchant" → "brand" in UI
- Ecosystem scenario matrix + eWards data requirements documented
- DemoDataSeeder provides realistic dashboard data
- Demo video rendered: `src/video/out/hyperlocal-v10.mp4` (10.4 MB, ~2:46)
- 32 screenshots captured across all 3 layers

---

## Files Modified / Created This Session

### Deleted
- `src/frontend/src/components/HelloWorld.vue` — unused Vite boilerplate
- `src/backend/app/Modules/IntegrationHub/Contracts/CouponAdapter.php` — dead interface

### Backend — New Files
- `app/Modules/CustomerPortal/Http/Controllers/CustomerAuthController.php` — OTP send + verify
- `app/Modules/CustomerPortal/Http/Controllers/CustomerRewardsController.php` — rewards + activity API
- `app/Modules/CustomerPortal/Services/OtpService.php` — OTP generation, caching, session tokens
- `app/Modules/CustomerPortal/Http/Middleware/CustomerAuth.php` — session token verification
- `app/Modules/CustomerPortal/README.md` — module documentation
- `database/migrations/2026_04_11_001_drop_promo_codes_table.php` — drops removed PromoCode table

### Backend — Modified
- `routes/api.php` — added customer portal routes, removed stale TODO, removed duplicate throttle
- `bootstrap/app.php` — registered `customer_auth` middleware
- `config/services.php` — added OTP rate limit config
- `.env` / `.env.example` — added `OTP_RATE_LIMIT_MAX`, `OTP_RATE_LIMIT_MINUTES`
- `app/Providers/AppServiceProvider.php` — registered OtpService
- `app/Modules/Admin/Http/Controllers/AuthController.php` — `/auth/me` now includes merchant city/category
- `app/Modules/IntegrationHub/Services/IntegrationResolverService.php` — removed `coupon()` method
- `app/Modules/Campaign/Constants/CampaignTemplate.php` — added `offer_announcement`, removed `coupon_issued`
- `app/Modules/Campaign/Jobs/DispatchCampaignSends.php` — renamed template
- `app/Modules/Campaign/README.md` — fixed CouponEngine reference
- `app/Modules/IntegrationHub/README.md` — removed CouponEngine reference
- `app/Modules/Partnership/Constants/PartnershipStatus.php` — ECOSYSTEM_INACTIVE from all states
- `app/Modules/Partnership/Listeners/AutoCloseOnEcosystemExit.php` — handles pre-live partnerships
- `database/seeders/DemoDataSeeder.php` — full rewrite with 3 partnerships, members, normalised phones

### Frontend — New Files
- `src/services/customerApi.ts` — axios instance for customer portal
- `src/stores/customerAuth.ts` — customer session state
- `src/modules/customer/views/CustomerLoginView.vue` — phone + OTP login
- `src/modules/customer/views/CustomerRewardsView.vue` — rewards dashboard
- `playwright.config.ts` + `e2e/*.spec.ts` — 48 E2E tests (11 test files)

### Frontend — Modified
- `src/router/index.ts` — added customer portal routes
- 10 Vue files — "merchant" → "brand" in user-facing labels
- `src/modules/superAdmin/views/MerchantListView.vue` — fixed pagination bug + field name mismatch

### Video
- `src/video/capture-all.js` — rewritten for 3-layer capture (SA + Merchant + Customer)
- `src/video/src/constants.ts` — added SA + Customer scenes, extended to 166 sec
- `src/video/src/Video.tsx` — added SA dashboard + Customer rewards screenshot slides
- `src/video/out/hyperlocal-v10.mp4` — rendered output (10.4 MB)
- `src/video/public/screenshots/final/` — 32 screenshots

### Docs
- `FLOWCHART.md` — 10+ corrections, CustomerPortal added
- `OPEN_DECISIONS.md` — 3-layer spec, ecosystem scenarios, eWards data requirements, D-011
- `VIDEO_SCRIPT.md` — new: 4-minute demo script with voiceover

---

## Bugs Found & Fixed

| Bug | Root Cause | Fix |
|-----|-----------|-----|
| SA Merchant List stuck on "Loading…" | `res.data.meta` undefined → Vue render crash | Read pagination from root response |
| SA Merchant List shows 0 credits | Field name mismatch `whatsapp_balance` vs `whatsapp_credits` | Fixed field name |
| FindPartnersView city auto-fill broken | `/auth/me` didn't include merchant data | Added merchant object to response |
| Ecosystem exit ignores pre-live partnerships | Listener only handled LIVE + PAUSED | Extended to all active states |
| `coupon_issued` template dead code | Template key in job but not in constants | Replaced with `offer_announcement` |
| Phone normalization inconsistent | CustomerPortal used raw regex, MemberService has normaliser | Use `MemberService::normalise()` everywhere |
| OTP rate limit hardcoded | Constant at 30 for dev | Made configurable via `OTP_RATE_LIMIT_MAX` env var |
| `dev_otp` in API response | Could leak in prod if `APP_DEBUG` misconfigured | Key now conditionally excluded entirely |
| E2E test flakes | Route-level throttle + ambiguous selectors | Removed duplicate throttle, scoped selectors to modal |

---

## Decisions Made (LOCKED)

| Decision | Status |
|----------|--------|
| PromoCode module removed entirely (table dropped) | LOCKED |
| `coupon_issued` template → `offer_announcement` | LOCKED |
| Ecosystem exit closes ALL active partnerships (including pre-live) | LOCKED |
| Customer portal uses phone + OTP auth | LOCKED |
| OTP credit charged to platform pool (not merchant) | LOCKED |
| "Merchant" → "Brand" in all user-facing UI labels | LOCKED |
| Phone normalisation: 10-digit Indian → prepend 91 | LOCKED |
| OTP rate limit configurable via env var | LOCKED |

---

## How to Run the Full System

```bash
# Backend
cd "src/backend"
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder
php artisan db:seed --class=DevelopmentSeeder
php artisan db:seed --class=DemoDataSeeder
php artisan serve
php artisan queue:work --queue=campaigns,default

# Frontend
cd "src/frontend"
npm run dev

# E2E Tests (48/48 passing)
cd "src/frontend"
npx playwright test --workers=1

# Screenshot Capture (32 screenshots)
cd "src/video"
node capture-all.js

# Render Demo Video
cd "src/video"
npx remotion render HyperlocalVideo out/hyperlocal.mp4 --codec h264 --crf 18
```

**Merchant login:** `alice@brewco.com` / `password`
**Super Admin:** `admin@hyperlocal.internal` / `changeme123`
**Customer portal:** `/my-rewards` → phone `9900001111` → OTP from dev hint

---

## What Was Built in This Session (Summary)

1. **Full E2E audit** — 48 Playwright tests covering all user flows across 3 layers
2. **Dead code cleanup** — HelloWorld.vue, CouponAdapter, CouponEngine refs, stale TODOs
3. **FLOWCHART corrections** — 10+ stale entries fixed, event listeners properly documented
4. **CustomerPortal module** — Phone+OTP auth, rewards API, session management, full frontend
5. **DemoDataSeeder** — 3 LIVE partnerships, 4 months of redemptions, members with balances
6. **"Merchant" → "Brand"** rename across all user-facing UI labels
7. **Ecosystem scenario matrix** — all state combinations documented in OPEN_DECISIONS.md
8. **eWards data requirements** — full spec of what's needed before going live
9. **Production fixes** — phone normalization, OTP rate limit config, dev_otp gating
10. **Demo video** — 32 screenshots + Remotion video rendered (hyperlocal-v10.mp4)

---

## Next Session Starting Point
- All 48 E2E tests passing, all 3 layers working
- Consider: add customer activity history tab to rewards page
- Consider: add `/my-rewards` link to WhatsApp campaign messages
- Consider: mobile-responsive testing
- Consider: duplicate partnership guard (two brands proposing simultaneously)
- eWards API spec needed before any adapter goes live
