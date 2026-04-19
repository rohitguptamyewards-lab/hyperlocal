<?php

/**
 * Migration: add_daily_and_lifetime_caps_to_partnership_terms
 *
 * Purpose  : Extends partnership_terms with granular daily, per-outlet, and
 *            lifetime cap controls so the rules engine can enforce intra-day
 *            and total-partnership spend ceilings without relying on the
 *            monthly cap_counters table.
 *
 * Owner module : Partnership / CapEnforcementService
 * Integration points:
 *   - CapEnforcementService::check() reads daily_cap_amount, outlet_daily_cap_amount,
 *     lifetime_cap_amount directly from partner_redemptions (no counter row needed).
 *   - ClaimService::issue() reads daily_transaction_count and outlet_daily_count
 *     directly from partner_claims.
 *   - RulesEngineService::computeBenefitAmount() reads outlet_per_bill_cap_amount.
 *   - notify_on_limit_hit / notify_partner_on_limit_hit consumed by AutoPauseOnCapExhausted listener.
 *   - pause_on_monthly_limit consumed by AutoPauseOnCapExhausted listener.
 *
 * Column glossary:
 *   daily_cap_amount           — max ₹ benefit issued for this partnership across
 *                                ALL outlets in a single calendar day (brand total).
 *   daily_cap_points           — points companion to daily_cap_amount.
 *   daily_transaction_count    — max claim TOKENS issued (not redeemed) per calendar
 *                                day (brand total across all source outlets).
 *   outlet_daily_cap_amount    — per-outlet ceiling on ₹ benefit redeemed in one day.
 *   outlet_daily_count         — per-outlet max tokens issued in one day.
 *   outlet_per_bill_cap_amount — per-outlet per-bill ceiling; overrides the brand-level
 *                                per_bill_cap_amount for that outlet when set.
 *   lifetime_cap_amount        — total ₹ cap for the entire partnership lifetime
 *                                (sum of all partner_redemptions.benefit_amount).
 *   lifetime_cap_points        — points companion to lifetime_cap_amount.
 *   notify_on_limit_hit        — send alert to the merchant admin when any cap is hit.
 *   notify_partner_on_limit_hit— also notify the partner-merchant admin when any cap is hit.
 *   pause_on_monthly_limit     — auto-pause token issuance when the monthly cap is exhausted.
 *
 * Reversible: YES — down() drops all added columns.
 * Additive-only: YES — no existing columns modified.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnership_terms', function (Blueprint $table): void {

            // ── Daily caps (brand-level) ──────────────────────────────────────
            $table->decimal('daily_cap_amount', 12, 2)
                  ->nullable()
                  ->after('monthly_cap_points')
                  ->comment('Max ₹ benefit issued for this partnership across all outlets in one calendar day');

            $table->decimal('daily_cap_points', 12, 2)
                  ->nullable()
                  ->after('daily_cap_amount')
                  ->comment('Points companion to daily_cap_amount');

            $table->unsignedInteger('daily_transaction_count')
                  ->nullable()
                  ->after('daily_cap_points')
                  ->comment('Max claim tokens issued per calendar day (brand total across all source outlets)');

            // ── Per-outlet daily caps ─────────────────────────────────────────
            $table->decimal('outlet_daily_cap_amount', 12, 2)
                  ->nullable()
                  ->after('daily_transaction_count')
                  ->comment('Per-outlet ceiling on ₹ benefit redeemed in one calendar day');

            $table->unsignedInteger('outlet_daily_count')
                  ->nullable()
                  ->after('outlet_daily_cap_amount')
                  ->comment('Per-outlet max claim tokens issued in one calendar day');

            // ── Per-outlet per-bill cap ───────────────────────────────────────
            $table->decimal('outlet_per_bill_cap_amount', 12, 2)
                  ->nullable()
                  ->after('outlet_daily_count')
                  ->comment('Per-outlet per-bill ceiling; overrides brand-level per_bill_cap_amount for that outlet');

            // ── Lifetime caps ─────────────────────────────────────────────────
            $table->decimal('lifetime_cap_amount', 12, 2)
                  ->nullable()
                  ->after('outlet_per_bill_cap_amount')
                  ->comment('Total ₹ cap for the entire partnership lifetime (sum of all redemptions)');

            $table->decimal('lifetime_cap_points', 12, 2)
                  ->nullable()
                  ->after('lifetime_cap_amount')
                  ->comment('Points companion to lifetime_cap_amount');

            // ── Notification and auto-pause flags ─────────────────────────────
            $table->boolean('notify_on_limit_hit')
                  ->default(false)
                  ->after('lifetime_cap_points')
                  ->comment('Notify merchant admin when any cap is hit');

            $table->boolean('notify_partner_on_limit_hit')
                  ->default(false)
                  ->after('notify_on_limit_hit')
                  ->comment('Also notify partner-merchant admin when any cap is hit');

            $table->boolean('pause_on_monthly_limit')
                  ->default(false)
                  ->after('notify_partner_on_limit_hit')
                  ->comment('Auto-pause token issuance when the monthly cap is exhausted');
        });
    }

    public function down(): void
    {
        Schema::table('partnership_terms', function (Blueprint $table): void {
            $table->dropColumn([
                'daily_cap_amount',
                'daily_cap_points',
                'daily_transaction_count',
                'outlet_daily_cap_amount',
                'outlet_daily_count',
                'outlet_per_bill_cap_amount',
                'lifetime_cap_amount',
                'lifetime_cap_points',
                'notify_on_limit_hit',
                'notify_partner_on_limit_hit',
                'pause_on_monthly_limit',
            ]);
        });
    }
};
