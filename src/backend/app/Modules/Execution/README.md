# Module: Execution

## What This Module Does
Handles the cashier-facing redemption flow. Looks up claim token, calls RulesEngine for eligibility, handles approval flow (auto / manager / OTP), executes redemption, stores rule snapshot, and fires downstream events. This is the most concurrency-sensitive module.

## What This Module Does NOT Do
- Does NOT evaluate rules itself — always delegates to RulesEngine
- Does NOT create ledger entries directly — fires event consumed by Ledger
- Does NOT compute attribution — fires event consumed by Analytics
- Does NOT send WhatsApp messages

## Tables Owned
- `partner_redemptions`

## Tables Read (Not Written)
- `partner_claims` (token lookup, then updates status to REDEEMED)
- `partnership_cap_counters` (via RulesEngine — SELECT FOR UPDATE)
- `partnership_terms` (via RulesEngine)
- `partnership_rules` (via RulesEngine)

## Tables Updated (Not Owned)
- `partner_claims.status` → set to REDEEMED on success

## Events Fired
| Event | Trigger |
|-------|---------|
| `partner.redemption.executed` | Successful redemption |
| `customer.first_visit_via_partnership` | If RulesEngine returns customer_type = new |
| `partnership.cap.exhausted` | If RulesEngine fires cap exhausted |

## Events Listened To
- None

## Idempotency
Every redemption requires a `transaction_id` (bill reference or UUID from POS).
`partner_redemptions` has `UNIQUE INDEX (merchant_id, transaction_id)`.
Duplicate submission returns the existing record, not an error.

## Rule Snapshot
On every successful redemption, the full resolved rules JSON is written to `partner_redemptions.rule_snapshot`. This is the source of truth for disputes. Never recalculate from current rules retroactively.

## Cashier UX Contract
The execution API returns:
```json
{
  "allowed": true,
  "benefit_amount": 150.00,
  "customer_type": "new",
  "requires_approval": false,
  "redemption_id": "uuid"
}
```
Or on block:
```json
{
  "allowed": false,
  "reason_code": "CAP_EXHAUSTED",
  "reason_display": "Monthly limit reached for this partnership",
  "fallback_help": "Contact your outlet manager"
}
```

## How to Run Locally
```bash
php artisan test --filter=ExecutionTest
```

## Key OPEN_DECISIONS Blocking This Module
- D-004 (race condition strategy — must be LOCKED before writing this module)
- D-009 (approval flow trigger threshold)
