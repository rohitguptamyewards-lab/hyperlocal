# Module: Migration

## What This Module Does
Handles the eventual data migration from this standalone build into eWards. Provides export scripts, ID mapping tables, data validation tooling, and a cutover checklist. Nothing in this module affects the standalone product — it is purely a migration utility layer.

## What This Module Does NOT Do
- Does NOT run automatically — all migration scripts are manual, gated, and reversible
- Does NOT modify eWards tables directly — produces export files / API payloads for eWards to consume
- Does NOT run in production standalone mode

## Tables Owned
- `ewrds_merchant_id_map` (standalone merchant_id → eWards merchant_id)
- `ewrds_customer_id_map` (standalone customer_id → eWards customer_id)
- `ewrds_migration_log` (what was exported, when, status)

## Tables Read (Not Written)
- All standalone tables — full read for export

## Migration Protocol (Draft — to be finalized when eWards spec received)

1. **Pre-migration:** Run validation report — no orphaned records, all FKs clean
2. **Dry run:** Export all entities to JSON files, validate against eWards schema
3. **ID mapping:** Resolve merchant/customer IDs via eWards lookup API
4. **Cutover:** Import to eWards in dependency order (same as migration order in DATA_MODEL.md)
5. **Verify:** Cross-check counts and spot-check records
6. **Rollback plan:** eWards import is additive-only; rollback = delete imported records by migration batch ID

## eWards Integration Points (PENDING — deferred until spec received)
- Loyalty point pickup: TBD
- Gift coupon issuance on redemption: TBD
- Audit log entries: TBD
- Merchant/outlet identity resolution: TBD

## How to Run Locally
```bash
# Validate data readiness
php artisan hyperlocal:migration-validate

# Export all entities
php artisan hyperlocal:migration-export --output=/tmp/hyperlocal-export

# Dry run against eWards (when spec available)
php artisan hyperlocal:migration-dryrun --target=ewrds_staging
```

## Key OPEN_DECISIONS Blocking This Module
- eWards integration contracts (deferred — will be received separately)
