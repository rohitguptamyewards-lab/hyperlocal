# Module: Campaign

## What this module does
Sends standardised WhatsApp broadcast messages to merchant's member base.
V1 constraint: no custom message bodies. Merchants fill template variables only.

## What it does NOT do
- Does not send custom/free-form WhatsApp messages (intentional V1 constraint)
- Does not manage loyalty points (→ LoyaltyBridge)
- Does not issue coupons — offer_announcement template lets merchants describe any offer as free text

## Tables it owns
- `campaigns`
- `campaign_sends`

## Tables it reads but does not write
- `members` (segment resolution + opt-in check)
- `partner_claims` (to scope segment to merchant's actual customers)

## Queue
Campaign sends run on the `campaigns` queue — separate from `default`.
Run: `php artisan queue:work --queue=campaigns`

## Template keys (V1)
See `CampaignTemplate::VALID_KEYS`. All templates must be Meta-approved before production.
Adding a new key: update `CampaignTemplate`, create MSG91 template, get Meta approval.

## Events it fires / listens to
None. (Future: fire `CampaignCompleted` event for analytics)

## How to run locally
1. Start the campaigns queue worker: `php artisan queue:work --queue=campaigns`
2. `GET /api/campaigns/templates` — see available templates
3. `POST /api/campaigns` — create draft
4. `POST /api/campaigns/{uuid}/run` — run immediately
