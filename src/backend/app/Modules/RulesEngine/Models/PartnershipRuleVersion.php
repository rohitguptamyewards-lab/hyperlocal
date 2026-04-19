<?php

namespace App\Modules\RulesEngine\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PartnershipRuleVersion — immutable snapshot of rules + terms in effect at a point in time.
 *
 * Purpose: Deduplicate rule snapshot storage on partner_redemptions (D-003 LOCKED 2026-04-10).
 * Owner module: RulesEngine
 * Table owned: partnership_rule_versions
 * Written by: Execution\Services\RedemptionService::resolveRuleVersion()
 * Read by: dispute resolution, analytics, ledger
 *
 * DO NOT soft-delete or update rows — this table is append-only.
 * UNIQUE KEY: (partnership_id, terms_version, rules_version)
 */
class PartnershipRuleVersion extends Model
{
    public $timestamps = false;

    protected $table = 'partnership_rule_versions';

    protected $fillable = [
        'partnership_id',
        'terms_version',
        'rules_version',
        'terms_snapshot',
        'rules_snapshot',
        'effective_from',
        'created_at',
    ];

    protected $casts = [
        'terms_snapshot'  => 'array',
        'rules_snapshot'  => 'array',
        'effective_from'  => 'datetime',
        'created_at'      => 'datetime',
    ];
}
