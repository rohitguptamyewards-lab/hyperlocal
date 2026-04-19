# Module: IntegrationHub

## What this module does
Manages pluggable external integrations per merchant. Resolves the correct adapter
(loyalty, coupon, POS) based on `merchant_integrations` config.

Never instantiate adapters directly — always go through `IntegrationResolverService`.

## What it does NOT do
- Does not store loyalty balances (→ LoyaltyBridge)
- Does not issue or redeem coupons directly (external POS owns coupon lifecycle)
- Does not implement the eWards full API — `EwardsAdapter` is a skeleton until spec arrives

## Tables it owns
- `merchant_integrations` — per-merchant provider config (encrypted)

## Tables it reads but does not write
- `members` (via adapters for external ID lookup)

## Adapter contracts
- `LoyaltyAdapter` — getBalance, award, deduct
- `PosAdapter`     — pushRedemption

## Events it fires / listens to
None.

## Supported providers (V1)
- `local` — own ledger (default, always available)
- `ewrds` — eWards (skeleton — awaiting API spec)

## How to add a new provider
1. Create `Adapters/YourProviderAdapter.php` implementing the relevant contract(s)
2. Add provider key to `IntegrationResolverService::buildAdapter()` match
3. Add to `IntegrationController::SUPPORTED_PROVIDERS`
