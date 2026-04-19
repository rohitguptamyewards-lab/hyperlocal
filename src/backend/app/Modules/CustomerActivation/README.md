# Module: CustomerActivation

## What This Module Does
Handles the customer-facing claim flow. Resolves QR scan to eligible partner offers, issues claim tokens with validity windows, delivers tokens via WhatsApp (or mock in local build), and manages token expiry.

## What This Module Does NOT Do
- Does NOT validate tokens at redemption time (→ Execution)
- Does NOT evaluate business rules (→ RulesEngine)
- Does NOT process payments or billing

## Tables Owned
- `partner_claims`

## Tables Read (Not Written)
- `partnerships` (to check LIVE status)
- `partnership_terms` (to surface offer preview to customer)
- `outlets` (to resolve QR source outlet)

## Events Fired
| Event | Trigger |
|-------|---------|
| `partner.claim.created` | Token issued to customer |

## Events Listened To
- `partnership.live` — activates QR for outlet

## Token Design
Pending OPEN_DECISIONS.md D-002.
Current assumption: short alphanumeric (e.g. `HLP-4X9K`), 8 characters, uppercase.
Stored in `partner_claims.token` with UNIQUE constraint.

## QR Code Design
Pending OPEN_DECISIONS.md D-005.
V1 assumption: static per-outlet QR. Payload = outlet UUID. Token issued after WhatsApp interaction.

## WhatsApp Integration
In local build: mock with a `WhatsAppLog` table entry (no real send).
Production: swap to real gateway when eWards spec received.

## Validity Window
Default: 48 hours from claim. Merchant-configurable in Phase 2.

## How to Run Locally
```bash
# Test claim flow end-to-end
php artisan hyperlocal:test-claim --outlet_id=1 --phone=9999999999
```

## Key OPEN_DECISIONS Blocking This Module
- D-002 (claim token format)
- D-005 (QR type: static vs dynamic)
- D-006 (WhatsApp gateway)
