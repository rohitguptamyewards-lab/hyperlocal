# Module: CustomerPortal

## What this module does
Public-facing customer rewards page. Customers verify via phone + OTP,
then see their loyalty points across all merchants and where they can redeem.

## What it does NOT do
- Does not manage partnerships (→ Partnership)
- Does not issue claims or process redemptions (→ CustomerActivation, Execution)
- Does not send campaigns (→ Campaign)
- Does not modify loyalty balances (→ LoyaltyBridge)

## Tables it owns
- None (uses cache for OTP + session storage)

## Tables it reads but does not write
- `members` (identity lookup)
- `member_loyalty_balances` (point balances)
- `merchants` (names, categories)
- `merchant_point_valuations` (₹ per point)
- `partnerships` + `partnership_participants` + `partnership_terms` (where points can be used)
- `outlets` (outlet names, addresses)
- `partner_claims` + `partner_redemptions` (activity history)

## Events it fires / listens to
None.

## Authentication
Phone + OTP. Sessions stored in cache (24h TTL).
OTP credit charged to platform pool (not merchant).
Rate limit: 3 OTP requests per phone per 10 minutes.

## How to run locally
1. Visit `http://localhost:3000/my-rewards`
2. Enter phone number → OTP appears in Laravel log (mock driver)
3. Enter OTP → rewards dashboard
