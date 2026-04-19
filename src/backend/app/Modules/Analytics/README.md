# Module: Analytics

## What This Module Does
Computes and stores attribution, retention, and ROI metrics. Runs async jobs to calculate 30/60/90-day customer retention after first-visit-via-partnership. Supplies data to merchant and ops dashboards.

## What This Module Does NOT Do
- Does NOT create ledger entries (→ Ledger)
- Does NOT serve real-time dashboards directly — pre-computes and caches aggregates
- Does NOT access raw customer PII beyond what is needed for retention calculation

## Tables Owned
- `partnership_attribution`

## Tables Read (Not Written)
- `partner_redemptions`
- `partnership_ledger_entries`
- `partnership_statements`
- Customer transaction history (local seed / eWards post-migration)

## Events Fired
- None

## Events Listened To
| Event | Action |
|-------|--------|
| `partner.redemption.executed` | Create / update attribution record |
| `customer.first_visit_via_partnership` | Set `first_visit_flag`, start retention window |

## Retention Computation
Scheduled jobs run nightly:
- `ComputeRetention30DJob` — runs 30 days after first visit
- `ComputeRetention60DJob` — runs 60 days after first visit
- `ComputeRetention90DJob` — runs 90 days after first visit

Each job updates `partnership_attribution.visit_count_Xd` and `revenue_Xd` from transaction history.

## North-Star Metric
`active_merchants_per_sq_km × cross_redemption_rate` — cluster density score.
Computed weekly and stored for dashboard trend view.

## How to Run Locally
```bash
php artisan hyperlocal:compute-retention --days=30
php artisan hyperlocal:compute-retention --days=60
php artisan hyperlocal:compute-retention --days=90
```

## Key OPEN_DECISIONS Blocking This Module
- None blocking — can start after Execution module is live
