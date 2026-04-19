# Module: Ledger

## What This Module Does
Maintains the virtual accounting trail for all partnership benefit flows. Creates ledger entries on every redemption and reversal. Aggregates period totals into monthly statements. Generates merchant-facing ROI snapshots.

## What This Module Does NOT Do
- Does NOT process actual payments or bank transfers
- Does NOT evaluate rules or caps
- Does NOT compute retention / attribution metrics (→ Analytics)

## Tables Owned
- `partnership_ledger_entries`
- `partnership_statements`

## Tables Read (Not Written)
- `partner_redemptions` (source of truth for amounts)
- `partnership_attribution` (for statement ROI fields — reads after Analytics computes)

## Events Fired
| Event | Trigger |
|-------|---------|
| `partnership.statement.generated` | Monthly statement finalized |

## Events Listened To
| Event | Action |
|-------|--------|
| `partner.redemption.executed` | Create ledger debit/credit entry |
| `partner.redemption.reversed` | Create reversal entry |

## Statement Generation
- Runs as a scheduled job on the 1st of each month for the prior period
- Marks status = FINAL when all redemptions for the period are confirmed
- Sends `partnership.statement.generated` event for merchant notification

## How to Run Locally
```bash
php artisan hyperlocal:generate-statements --period=2026-03
```

## Key OPEN_DECISIONS Blocking This Module
- D-003 (rule snapshot format — needed before redemption record structure is final)
