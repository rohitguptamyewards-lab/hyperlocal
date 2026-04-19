# Module: Enablement

## What This Module Does
Manages staff continuity and dormancy detection for active partnerships. Tracks last training date and last usage per outlet. Fires dormancy alerts when a LIVE partnership has had no redemptions beyond the configured threshold. Provides re-activation flow for new staff.

## What This Module Does NOT Do
- Does NOT manage partnership lifecycle status (→ Partnership module)
- Does NOT send notifications directly — fires events consumed by notification service
- Does NOT evaluate rules

## Tables Owned
- `partnership_staff_enablement`

## Tables Read (Not Written)
- `partnerships` (to check LIVE status)
- `partner_redemptions` (to determine last usage per outlet)

## Events Fired
| Event | Trigger |
|-------|---------|
| `partnership.dormant` | No redemption at outlet beyond dormancy threshold |

## Events Listened To
| Event | Action |
|-------|--------|
| `partnership.live` | Create `partnership_staff_enablement` row per outlet |
| `partner.redemption.executed` | Update `last_used_at` for the outlet |

## Dormancy Detection
Scheduled job runs daily:
- Checks all LIVE partnerships
- For each outlet: if `last_used_at` > dormancy threshold → set `is_dormant = true`
- Default threshold: 14 days (configurable per merchant)
- Fires `partnership.dormant` once per dormancy cycle (not repeatedly)

## How to Run Locally
```bash
php artisan hyperlocal:check-dormancy
```

## Key OPEN_DECISIONS Blocking This Module
- None blocking
