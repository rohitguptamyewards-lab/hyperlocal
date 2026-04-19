<?php

namespace App\Modules\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * LedgerEntry — virtual accounting row for one side of a redemption.
 *
 * Owner module: Ledger
 * Tables owned: partner_ledger_entries
 * Created by: CreateLedgerEntryOnRedemption listener
 * Read by: StatementService, Analytics dashboard
 *
 * entry_type constants:
 *   BENEFIT_GIVEN   — merchant who gave the discount (target side)
 *   REFERRAL_CREDIT — merchant whose customer was referred (source side)
 */
class LedgerEntry extends Model
{
    public const BENEFIT_GIVEN   = 'benefit_given';
    public const REFERRAL_CREDIT = 'referral_credit';

    protected $table = 'partner_ledger_entries';

    protected $fillable = [
        'uuid', 'partnership_id', 'redemption_id', 'merchant_id',
        'outlet_id', 'entry_type', 'amount', 'period_month', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'period_month' => 'date',
    ];

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeForPartnership(Builder $query, int $partnershipId): Builder
    {
        return $query->where('partnership_id', $partnershipId);
    }

    public function scopeInPeriod(Builder $query, string $yearMonth): Builder
    {
        // yearMonth format: YYYY-MM
        return $query->whereRaw("strftime('%Y-%m', period_month) = ?", [$yearMonth]);
    }
}
