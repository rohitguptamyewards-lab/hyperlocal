# Data Model — Hyperlocal Partnership Network

> **DO NOT modify table names or primary key conventions without updating FLOWCHART.md and all module READMEs.**
> Every table carries `merchant_id` for multi-tenant isolation — never query without it.
> All tables use soft deletes (`deleted_at`) and audit columns (`created_by`, `updated_by`).

---

## Last Updated: 2026-04-09

---

## Conventions

| Convention | Value |
|-----------|-------|
| Primary keys | `BIGINT UNSIGNED AUTO_INCREMENT` |
| UUIDs (public-facing) | `uuid CHAR(36)` — separate from internal ID |
| Tenant isolation | `merchant_id BIGINT UNSIGNED NOT NULL` on every table |
| Soft deletes | `deleted_at TIMESTAMP NULL` on every table |
| Audit columns | `created_by`, `updated_by` BIGINT on every table |
| Timestamps | `created_at`, `updated_at` TIMESTAMP on every table |
| Enums | Use `TINYINT` with app-level constants — not MySQL ENUM |
| JSON | MySQL 8 `JSON` column for flexible payloads |
| Money | `DECIMAL(12,2)` — never FLOAT |

---

## Entity Relationship Overview

```
merchants (eWards — read only in standalone)
    │
    ├──< partnership_participants >──┐
    │                               │
    └──< partnerships               │
              │                     │
              ├──< partnership_terms│
              ├──< partnership_rules│
              ├──< partnership_agreements
              ├──< partner_claims
              │         │
              │         └──< partner_redemptions
              │                   │
              │                   └──< partnership_ledger_entries
              │
              ├──< partnership_statements
              ├──< partnership_attribution
              ├──< partnership_staff_enablement
              └──< partner_recommendations
```

---

## Table Definitions

---

### `partnerships`
Core partnership record. One row per partnership.

```sql
CREATE TABLE partnerships (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36)        NOT NULL UNIQUE,
    merchant_id         BIGINT UNSIGNED NOT NULL,       -- owning merchant (proposer)
    name                VARCHAR(255)    NOT NULL,
    scope_type          TINYINT         NOT NULL,       -- 1=outlet, 2=city, 3=brand
    status              TINYINT         NOT NULL,       -- see status constants
    template_id         BIGINT UNSIGNED NULL,           -- FK partnership_templates
    agreement_id        BIGINT UNSIGNED NULL,           -- FK partnership_agreements
    start_at            TIMESTAMP       NULL,
    end_at              TIMESTAMP       NULL,
    paused_at           TIMESTAMP       NULL,
    paused_reason       VARCHAR(500)    NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    updated_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP       NULL,

    INDEX idx_merchant_status   (merchant_id, status, deleted_at),
    INDEX idx_merchant_dates    (merchant_id, start_at, end_at),
    INDEX idx_uuid              (uuid)
);
```

**Status constants:**
```
1 = SUGGESTED
2 = REQUESTED
3 = NEGOTIATING
4 = AGREED
5 = LIVE
6 = PAUSED
7 = EXPIRED
8 = REJECTED
```

---

### `partnership_participants`
Who is in the partnership (both sides). Each merchant/outlet gets a row.

**Scope rules (LOCKED — D-008):**
- `outlet_id = NULL` → this merchant's ALL outlets are in scope (brand-level)
- `outlet_id = specific` → only that outlet is in scope (outlet-level)
- Mixed is valid: one side brand-wide (`NULL`), other side specific outlets (multiple rows)
- A national brand running outlet-specific partnerships in different cities creates separate `partnerships` records, each with its local outlet participant rows

```sql
CREATE TABLE partnership_participants (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partnership_id      BIGINT UNSIGNED NOT NULL,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    outlet_id           BIGINT UNSIGNED NULL,           -- NULL = ALL outlets of this merchant (brand-level)
                                                        -- Specific ID = outlet-level only
    role                TINYINT         NOT NULL,       -- 1=proposer, 2=acceptor
    approval_status     TINYINT         NOT NULL,       -- 1=pending, 2=approved, 3=rejected
    approved_by         BIGINT UNSIGNED NULL,
    approved_at         TIMESTAMP       NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    updated_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP       NULL,

    INDEX idx_partnership       (partnership_id, deleted_at),
    INDEX idx_merchant_outlet   (merchant_id, outlet_id, deleted_at),
    INDEX idx_merchant_role     (merchant_id, role, approval_status)
);
```

---

### `partnership_terms`
Visible V1 commercial configuration. One row per partnership.

```sql
CREATE TABLE partnership_terms (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partnership_id          BIGINT UNSIGNED NOT NULL UNIQUE,
    merchant_id             BIGINT UNSIGNED NOT NULL,
    per_bill_cap_amount     DECIMAL(12,2)   NULL,       -- max benefit per bill (₹)
    per_bill_cap_percent    DECIMAL(5,2)    NULL,       -- max benefit as % of bill
    min_bill_amount         DECIMAL(12,2)   NULL,       -- minimum bill to qualify
    monthly_cap_amount      DECIMAL(12,2)   NULL,       -- global monthly ceiling (₹)
    partner_monthly_cap     DECIMAL(12,2)   NULL,       -- per-partner monthly ceiling
    outlet_monthly_cap      DECIMAL(12,2)   NULL,       -- per-outlet monthly ceiling
    approval_mode           TINYINT         NOT NULL DEFAULT 1, -- 1=auto, 2=manager, 3=otp
    approval_threshold      DECIMAL(12,2)   NULL,       -- auto-approve below this amount
    version                 INT UNSIGNED    NOT NULL DEFAULT 1,
    created_by              BIGINT UNSIGNED NOT NULL,
    updated_by              BIGINT UNSIGNED NOT NULL,
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL,

    INDEX idx_merchant          (merchant_id, deleted_at)
);
```

---

### `partnership_rules`
Advanced and conditional rules. One row per partnership.

```sql
CREATE TABLE partnership_rules (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partnership_id          BIGINT UNSIGNED NOT NULL UNIQUE,
    merchant_id             BIGINT UNSIGNED NOT NULL,
    customer_type_rules     JSON            NULL,
    -- Structure: {"new": {"cap_multiplier": 1.5}, "existing": {"cap_multiplier": 0.5}, "reactivated": {"cap_multiplier": 1.2}}
    inactivity_days         INT UNSIGNED    NOT NULL DEFAULT 90,  -- days until "reactivated" classification
    blackout_rules          JSON            NULL,
    -- Structure: [{"type": "date", "value": "2026-12-25"}, {"type": "weekday", "value": [6,7]}]
    time_band_rules         JSON            NULL,
    -- Structure: [{"days": [1,2,3,4,5], "from": "09:00", "to": "17:00"}]
    stacking_rules          JSON            NULL,
    -- Structure: {"allow_stacking": false, "blocked_offer_types": [1,2]}
    uses_per_customer       INT UNSIGNED    NULL,       -- NULL = unlimited
    cooling_period_days     INT UNSIGNED    NULL,       -- days between uses per customer
    first_time_only         TINYINT(1)      NOT NULL DEFAULT 0,
    version                 INT UNSIGNED    NOT NULL DEFAULT 1,
    created_by              BIGINT UNSIGNED NOT NULL,
    updated_by              BIGINT UNSIGNED NOT NULL,
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL,

    INDEX idx_merchant          (merchant_id, deleted_at)
);
```

---

### `partnership_agreements`
Legal/commercial acceptance record.

```sql
CREATE TABLE partnership_agreements (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36)        NOT NULL UNIQUE,
    partnership_id      BIGINT UNSIGNED NOT NULL,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    version             VARCHAR(20)     NOT NULL,
    file_path           VARCHAR(1000)   NULL,           -- S3 path or local storage
    accepted_by         BIGINT UNSIGNED NULL,
    accepted_at         TIMESTAMP       NULL,
    ip_address          VARCHAR(45)     NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    updated_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP       NULL,

    INDEX idx_partnership       (partnership_id),
    INDEX idx_merchant          (merchant_id, deleted_at)
);
```

---

### `partner_claims`
Customer claim tokens. High-volume table — partitioning required at scale.

```sql
CREATE TABLE partner_claims (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36)        NOT NULL UNIQUE,
    merchant_id         BIGINT UNSIGNED NOT NULL,       -- redeeming merchant
    partnership_id      BIGINT UNSIGNED NOT NULL,
    source_outlet_id    BIGINT UNSIGNED NOT NULL,       -- where QR was scanned
    target_outlet_id    BIGINT UNSIGNED NOT NULL,       -- where benefit is redeemed
    customer_id         BIGINT UNSIGNED NULL,           -- NULL if anonymous at claim time
    customer_phone      VARCHAR(20)     NULL,           -- for WhatsApp delivery
    token               VARCHAR(20)     NOT NULL UNIQUE,
    status              TINYINT         NOT NULL,       -- 1=issued, 2=redeemed, 3=expired, 4=cancelled
    issued_at           TIMESTAMP       NOT NULL,
    expires_at          TIMESTAMP       NOT NULL,
    redeemed_at         TIMESTAMP       NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    updated_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP       NULL,

    INDEX idx_merchant_status       (merchant_id, status, deleted_at),
    INDEX idx_partnership_status    (partnership_id, status),
    INDEX idx_customer              (customer_id, status),
    INDEX idx_token                 (token),            -- fast cashier lookup
    INDEX idx_expires               (expires_at, status) -- expiry job
);
```

---

### `partner_redemptions`
Executed benefit usage. Immutable after creation — never soft-delete.

```sql
CREATE TABLE partner_redemptions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36)        NOT NULL UNIQUE,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    partnership_id      BIGINT UNSIGNED NOT NULL,
    claim_id            BIGINT UNSIGNED NOT NULL,
    outlet_id           BIGINT UNSIGNED NOT NULL,
    customer_id         BIGINT UNSIGNED NULL,
    bill_id             VARCHAR(100)    NULL,           -- external bill reference
    transaction_id      VARCHAR(100)    NULL,           -- idempotency key
    bill_amount         DECIMAL(12,2)   NOT NULL,
    benefit_amount      DECIMAL(12,2)   NOT NULL,
    customer_type       TINYINT         NOT NULL,       -- 1=new, 2=existing, 3=reactivated
    rule_snapshot       JSON            NOT NULL,       -- exact rules at time of execution
    approved_by         BIGINT UNSIGNED NULL,
    approval_method     TINYINT         NULL,           -- 1=auto, 2=manager, 3=otp
    status              TINYINT         NOT NULL DEFAULT 1, -- 1=completed, 2=reversed, 3=disputed
    reversed_at         TIMESTAMP       NULL,
    reversed_reason     VARCHAR(500)    NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    updated_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_idempotency        (merchant_id, transaction_id),
    INDEX idx_merchant_partnership      (merchant_id, partnership_id, created_at),
    INDEX idx_customer_type             (customer_id, customer_type),
    INDEX idx_outlet_date               (outlet_id, created_at)
);
```

---

### `partnership_cap_counters`
Atomic counters for cap enforcement. Separate table enables row-level locking.

```sql
CREATE TABLE partnership_cap_counters (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    partnership_id      BIGINT UNSIGNED NOT NULL,
    outlet_id           BIGINT UNSIGNED NULL,           -- NULL = partnership-level counter
    period_year         SMALLINT UNSIGNED NOT NULL,
    period_month        TINYINT UNSIGNED NOT NULL,
    amount_used         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    redemption_count    INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_counter_key (merchant_id, partnership_id, outlet_id, period_year, period_month),
    INDEX idx_merchant_period    (merchant_id, period_year, period_month)
);
```

> **Race condition mitigation:** Use `SELECT ... FOR UPDATE` on this row before any redemption. See OPEN_DECISIONS.md D-004.

---

### `partnership_ledger_entries`
Virtual accounting trail. One entry per redemption event (debit/credit).

```sql
CREATE TABLE partnership_ledger_entries (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36)        NOT NULL UNIQUE,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    partnership_id      BIGINT UNSIGNED NOT NULL,
    redemption_id       BIGINT UNSIGNED NULL,
    entry_type          TINYINT         NOT NULL,       -- 1=debit, 2=credit, 3=reversal
    amount              DECIMAL(12,2)   NOT NULL,
    source_merchant_id  BIGINT UNSIGNED NOT NULL,
    target_merchant_id  BIGINT UNSIGNED NOT NULL,
    period_year         SMALLINT UNSIGNED NOT NULL,
    period_month        TINYINT UNSIGNED NOT NULL,
    statement_id        BIGINT UNSIGNED NULL,
    notes               VARCHAR(500)    NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_merchant_period       (merchant_id, period_year, period_month),
    INDEX idx_partnership_period    (partnership_id, period_year, period_month),
    INDEX idx_statement             (statement_id)
);
```

---

### `partnership_statements`
Merchant-facing monthly settlement / ROI snapshot.

```sql
CREATE TABLE partnership_statements (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                    CHAR(36)        NOT NULL UNIQUE,
    merchant_id             BIGINT UNSIGNED NOT NULL,
    partnership_id          BIGINT UNSIGNED NOT NULL,
    period_year             SMALLINT UNSIGNED NOT NULL,
    period_month            TINYINT UNSIGNED NOT NULL,
    status                  TINYINT         NOT NULL DEFAULT 1, -- 1=draft, 2=final, 3=sent, 4=acknowledged
    total_redemptions       INT UNSIGNED    NOT NULL DEFAULT 0,
    total_benefit_given     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    total_benefit_received  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    new_customers_acquired  INT UNSIGNED    NOT NULL DEFAULT 0,
    reactivated_customers   INT UNSIGNED    NOT NULL DEFAULT 0,
    retained_30d            INT UNSIGNED    NOT NULL DEFAULT 0,
    retained_60d            INT UNSIGNED    NOT NULL DEFAULT 0,
    retained_90d            INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_post_first_visit DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    net_contribution        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    finalized_at            TIMESTAMP       NULL,
    sent_at                 TIMESTAMP       NULL,
    created_by              BIGINT UNSIGNED NOT NULL,
    updated_by              BIGINT UNSIGNED NOT NULL,
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL,

    UNIQUE INDEX idx_period_key     (merchant_id, partnership_id, period_year, period_month),
    INDEX idx_merchant_status       (merchant_id, status, deleted_at)
);
```

---

### `partnership_attribution`
Retention and source tracking. One row per customer per partnership.

```sql
CREATE TABLE partnership_attribution (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id                 BIGINT UNSIGNED NOT NULL,
    partnership_id              BIGINT UNSIGNED NOT NULL,
    customer_id                 BIGINT UNSIGNED NOT NULL,
    first_visit_redemption_id   BIGINT UNSIGNED NOT NULL,
    first_visit_at              TIMESTAMP       NOT NULL,
    customer_type_at_entry      TINYINT         NOT NULL,   -- 1=new, 2=existing, 3=reactivated
    visit_count_30d             INT UNSIGNED    NOT NULL DEFAULT 0,
    visit_count_60d             INT UNSIGNED    NOT NULL DEFAULT 0,
    visit_count_90d             INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_30d                 DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    revenue_60d                 DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    revenue_90d                 DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    last_computed_at            TIMESTAMP       NULL,
    created_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_customer_key       (merchant_id, partnership_id, customer_id),
    INDEX idx_merchant_partnership      (merchant_id, partnership_id),
    INDEX idx_first_visit_type          (merchant_id, customer_type_at_entry, first_visit_at)
);
```

---

### `partnership_staff_enablement`
Staff continuity layer per outlet.

```sql
CREATE TABLE partnership_staff_enablement (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    partnership_id      BIGINT UNSIGNED NOT NULL,
    outlet_id           BIGINT UNSIGNED NOT NULL,
    last_training_at    TIMESTAMP       NULL,
    last_used_at        TIMESTAMP       NULL,
    is_dormant          TINYINT(1)      NOT NULL DEFAULT 0,
    dormant_since       TIMESTAMP       NULL,
    dormancy_alert_sent TINYINT(1)      NOT NULL DEFAULT 0,
    created_by          BIGINT UNSIGNED NOT NULL,
    updated_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP       NULL,

    UNIQUE INDEX idx_outlet_key         (merchant_id, partnership_id, outlet_id),
    INDEX idx_dormant                   (is_dormant, dormancy_alert_sent)
);
```

---

### `partner_recommendations`
Pre-computed partner suggestions for merchant onboarding.

```sql
CREATE TABLE partner_recommendations (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    recommended_merchant_id BIGINT UNSIGNED NOT NULL,
    fit_score           DECIMAL(5,4)    NOT NULL,       -- 0.0000 to 1.0000
    rationale           VARCHAR(1000)   NULL,
    cluster_id          BIGINT UNSIGNED NULL,
    confidence_tier     TINYINT         NOT NULL,       -- 1=high, 2=medium, 3=low
    status              TINYINT         NOT NULL DEFAULT 1, -- 1=active, 2=dismissed, 3=converted
    dismissed_at        TIMESTAMP       NULL,
    converted_at        TIMESTAMP       NULL,
    computed_at         TIMESTAMP       NOT NULL,
    expires_at          TIMESTAMP       NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_merchant_score        (merchant_id, fit_score DESC, status),
    INDEX idx_cluster               (cluster_id, status),
    INDEX idx_expires               (expires_at, status)
);
```

---

## Tables Owned by This Module vs Read-Only

| Table | Owned By | Read By |
|-------|---------|---------|
| `partnerships` | Partnership module | All modules |
| `partnership_participants` | Partnership module | Execution, Analytics |
| `partnership_terms` | Partnership module | RulesEngine, Execution |
| `partnership_rules` | RulesEngine module | Execution |
| `partnership_agreements` | Partnership module | — |
| `partner_claims` | CustomerActivation module | Execution |
| `partner_redemptions` | Execution module | Ledger, Analytics |
| `partnership_cap_counters` | RulesEngine module | Execution |
| `partnership_ledger_entries` | Ledger module | Analytics |
| `partnership_statements` | Ledger module | Analytics, Frontend |
| `partnership_attribution` | Analytics module | Dashboard |
| `partnership_staff_enablement` | Enablement module | Ops dashboard |
| `partner_recommendations` | Discovery module | Partnership module |

---

## Scope Resolution Query (D-008)

At redemption time, RulesEngine resolves active partnerships for an outlet like this:

```sql
SELECT p.*
FROM partnerships p
JOIN partnership_participants pp ON pp.partnership_id = p.id
WHERE p.status = 5                          -- LIVE
  AND p.deleted_at IS NULL
  AND pp.merchant_id = :redeeming_merchant_id
  AND (
      pp.outlet_id = :redeeming_outlet_id   -- explicit outlet match
      OR pp.outlet_id IS NULL               -- brand-wide match
  )
  AND pp.approval_status = 2               -- approved
  AND pp.deleted_at IS NULL
  AND (p.start_at IS NULL OR p.start_at <= NOW())
  AND (p.end_at IS NULL OR p.end_at >= NOW());
```

---

## Migration Order (Dependencies)

```
1. partnerships
2. partnership_participants
3. partnership_agreements
4. partnership_terms
5. partnership_rules
6. partnership_cap_counters
7. partner_claims
8. partner_redemptions
9. partnership_ledger_entries
10. partnership_statements
11. partnership_attribution
12. partnership_staff_enablement
13. partner_recommendations
```

---

## eWards Migration Notes

> These notes will be expanded when eWards integration spec is received.

- `merchant_id` in standalone = local `merchants.id`
- On migration: create a `ewrds_merchant_id_map` table to translate standalone IDs → eWards IDs
- `customer_id` in standalone = local identifier; will need phone/email resolution against eWards customer table
- All `deleted_at` rows are excluded from migration exports
