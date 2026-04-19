# Module: Partnership

## What This Module Does
Owns the core partnership entity and its lifecycle. Handles partnership creation, proposal flow, status transitions, agreement capture, outlet participant management, and lifecycle events (requested → live → paused → expired).

## What This Module Does NOT Do
- Does NOT evaluate rules or caps (→ RulesEngine)
- Does NOT issue claim tokens (→ CustomerActivation)
- Does NOT process redemptions (→ Execution)
- Does NOT compute attribution or ROI (→ Analytics)
- Does NOT suggest partners (→ Discovery)

## Tables Owned
- `partnerships`
- `partnership_participants`
- `partnership_agreements`
- `partnership_terms` (writes initial config; RulesEngine reads)

## Tables Read (Not Written)
- `partner_recommendations` (Discovery module — read to convert suggestion to request)
- `merchants`, `outlets` (eWards / local seed — read only)

## Events Fired
| Event | Trigger |
|-------|---------|
| `partnership.requested` | Proposal submitted |
| `partnership.accepted` | Counter-party accepts |
| `partnership.live` | Status transitions to LIVE |
| `partnership.paused` | Merchant pauses |
| `partnership.expired` | End date passed or manual close |

## Events Listened To
- None (entry point module)

## State Machine
```
SUGGESTED → REQUESTED → NEGOTIATING → AGREED → LIVE → PAUSED → EXPIRED
                                              ↘ REJECTED
```

## How to Run Locally
```bash
cd src/backend
php artisan migrate --path=database/migrations/Partnership
php artisan db:seed --class=PartnershipSeeder
```

## Key OPEN_DECISIONS Blocking This Module
- D-001 (MySQL environment)
- D-008 (scope resolution model)
