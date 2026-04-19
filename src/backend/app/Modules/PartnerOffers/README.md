# Module: PartnerOffers

## What this module does
Enables brands to create coupon/offer codes that appear on partner brands' digital bill pages.
Two distribution channels: partnership-level attachments and network-level publications.

## What it does NOT do
- Does not process redemptions (POS handles that externally)
- Does not manage loyalty points (→ LoyaltyBridge)
- Does not send WhatsApp campaigns (→ Campaign)
- Does not build the eWards digital bill page (eWards links to our URL)

## Tables it owns
- `partner_offers` — the offer itself (title, code, discount, display template)
- `partner_offer_attachments` — links offers to partnerships
- `partner_offer_network_publications` — publishes offers to networks
- `partner_offer_impressions` — analytics: offer shown on bill page
- `partner_offer_claims` — analytics: coupon code copied

## Tables it reads but does not write
- `merchants` (bill_offers_enabled, bill_offers_display_mode)
- `partnerships` + `partnership_participants` (LIVE check, bill_offers_enabled flag)
- `network_memberships` (membership check)

## Switch hierarchy (all must be ON for an offer to appear)
1. `merchants.bill_offers_enabled` (master, default OFF)
2. `partnership_participants.bill_offers_enabled` (per partnership, default ON)
3. `partner_offers.status` (per offer, default ACTIVE)
4. `partner_offer_attachments.is_active` (per attachment, default ON)

## Events it fires / listens to
None.

## How to run locally
1. Create an offer: `POST /api/partner-offers`
2. Attach to a partnership: `POST /api/partner-offers/{uuid}/attach`
3. Enable bill offers for the receiving merchant: `POST /api/merchant/settings/bill-offers`
4. View the public page: `http://localhost:3000/bill-offers/{merchantUuid}`
