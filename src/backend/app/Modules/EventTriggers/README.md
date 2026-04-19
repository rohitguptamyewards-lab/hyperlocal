# Module: EventTriggers

## What this module does
Event ingestion infrastructure that lets any merchant fire events from their website, ecommerce store, or backend — which then activate partnership offers, campaigns, or customer rewards.

## What it does NOT do
- Does not own partner offers (→ PartnerOffers)
- Does not manage campaigns (→ Campaign)
- Does not process POS redemptions (→ Execution)

## Tables it owns
- `event_sources` — merchant connections (API keys, source type)
- `event_triggers` — rules: event → conditions → action
- `event_log` — full event audit trail

## Supported event sources
- Website pixel/URL trigger
- Server-to-server signed API
- Shopify order webhook
- WooCommerce order webhook
- eWards POS (native)

## How to run locally
1. Create event source: `POST /api/event-sources`
2. Copy the `merchant_key` from response
3. Create trigger: `POST /api/event-triggers`
4. Fire test event: `GET /api/events/pixel/{merchantKey}?event=transaction_completed&amount=500&phone=9900001111`
5. Check log: `GET /api/event-log`
6. Queue worker: `php artisan queue:work --queue=events`
