# Module: LoyaltyBridge

## What this module does
Single entry point for all loyalty balance operations.
Our `member_loyalty_balances` table is the source of truth at runtime.

Flow:
- `getBalance` → local cache → refresh from external if stale (>60 min) → return
- `award`  → write local → push to external (best-effort, failures logged not thrown)
- `deduct` → write local → push to external (best-effort)

## What it does NOT do
- Does not manage member identity (→ Member module)
- Does not handle partnership earn/redeem accounting (→ Ledger module)
  LoyaltyBridge handles raw balance; Ledger handles partnership-level statements

## Tables it owns
- `member_loyalty_balances`

## Tables it reads but does not write
- `members` (to resolve phone → member_id)
- `merchant_integrations` (via IntegrationResolverService)

## Events it fires / listens to
None. (Future: listen to `RedemptionExecuted` to auto-award loyalty on partnership earn)

## How to run locally
No setup needed — uses local adapter by default.
To test external sync: configure a `merchant_integrations` row with `is_loyalty_source=true`.
