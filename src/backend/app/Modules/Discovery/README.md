# Module: Discovery

## What This Module Does
Computes and serves partner recommendations for merchant onboarding. Auto-suggests 3–5 nearby, complementary merchants based on category fit, cluster density, and location proximity. Supports search, filters, and map/list views for manual exploration.

## What This Module Does NOT Do
- Does NOT create partnerships (→ Partnership module)
- Does NOT use real-time AI in V1 (recommendations are pre-computed batch jobs)
- Does NOT access raw customer data

## Tables Owned
- `partner_recommendations`

## Tables Read (Not Written)
- `merchants`, `outlets` (for category, location, sector data)
- `partnerships` (to exclude already-active pairs)

## Events Fired
- None in V1

## Events Listened To
- `partnership.requested` — marks recommendation as `converted`
- `partnership.rejected` — marks recommendation as `dismissed`

## Recommendation Scoring (V1)
Pre-computed nightly batch. Factors:
1. Category complementarity (non-competing, high cross-visit potential)
2. Geographic proximity (outlet distance in km)
3. Cluster density (how many active partnerships in the same zone)
4. Historical redemption rate in cluster (Phase 2)

## How to Run Locally
```bash
php artisan hyperlocal:compute-recommendations --merchant_id=1
```

## Key OPEN_DECISIONS Blocking This Module
- D-001 (MySQL environment)
