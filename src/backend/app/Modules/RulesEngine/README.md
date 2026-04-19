# Module: RulesEngine

## What This Module Does
Evaluates all eligibility, cap, and restriction rules for a given redemption attempt. Owns cap counters and enforces per-bill, monthly, outlet, and partnership-level ceilings. Classifies customer type (new / existing / reactivated). Provides a single `evaluate(RedemptionContext $ctx): RulesResult` interface consumed by the Execution module.

## What This Module Does NOT Do
- Does NOT execute redemptions (→ Execution)
- Does NOT issue tokens (→ CustomerActivation)
- Does NOT store transaction history (→ Ledger)

## Tables Owned
- `partnership_rules`
- `partnership_cap_counters`

## Tables Read (Not Written)
- `partnership_terms` (cap configuration)
- `partner_claims` (token validity)
- `partner_redemptions` (historical usage for cooling period / uses-per-customer check)
- `customers` / transaction history (for customer-type classification)

## Events Fired
| Event | Trigger |
|-------|---------|
| `partnership.cap.exhausted` | Monthly/partner/outlet cap reaches ceiling |

## Events Listened To
- None

## Core Interface
```php
// Single entry point — Execution module calls this
$result = RulesEngine::evaluate(new RedemptionContext(
    partnership_id: $id,
    outlet_id: $outletId,
    customer_id: $customerId,
    bill_amount: $amount,
    claim_token: $token,
    attempted_at: now(),
));

// Returns:
// $result->allowed  (bool)
// $result->reason   (string reason code if blocked)
// $result->customer_type (new|existing|reactivated)
// $result->max_benefit_amount (calculated cap for this bill)
```

## Race Condition Protocol
Cap counter updates use `SELECT ... FOR UPDATE` to prevent double-spend.
See OPEN_DECISIONS.md D-004 before building cap enforcement.

## How to Run Locally
```bash
php artisan test --filter=RulesEngineTest
```

## Key OPEN_DECISIONS Blocking This Module
- D-001 (MySQL environment)
- D-004 (cap exhaustion race condition strategy)
- D-007 (inactivity threshold for reactivated customer)
