# Frontend Module: Analytics / Dashboard

## What This Module Does
Merchant-facing ROI and partnership health dashboards. Displays revenue, new customers acquired, 30/60/90-day retention, reciprocity scores, cap utilization, and renewal signals.

## Key Views
- Partnership ROI card (hero metrics)
- Retention funnel (first visit → 30d → 60d → 90d)
- Cap utilization gauge per partnership
- Reciprocity balance (benefit given vs received)
- Cluster health map (Phase 2)

## API Dependencies
- `GET /api/analytics/dashboard/{partnership_id}`
- `GET /api/analytics/statement/{period}`
- `GET /api/analytics/cluster` (Phase 2)
