<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership
 * Adds points-denomination companion columns to partnership_terms.
 *
 * All existing ₹ columns are unchanged and remain the authoritative values
 * used by the execution engine. Points columns are display/audit companions:
 *
 *   per_bill_cap_points          — original per-bill cap expressed in source-merchant points
 *   monthly_cap_points           — original monthly cap in points
 *   min_bill_points              — original min-bill threshold in points
 *   rupees_per_point_at_agreement — locked rate used to convert points → ₹ at agreement time
 *
 * If a points column is NULL, the ₹ column was set directly.
 * If a points column is SET, the ₹ column = points × rupees_per_point_at_agreement (locked).
 *
 * Reversible: down() drops the four new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnership_terms', function (Blueprint $table) {
            $table->decimal('per_bill_cap_points', 12, 2)->nullable()
                ->after('per_bill_cap_amount')
                ->comment('Per-bill cap expressed in source-merchant points (NULL = ₹ was used)');
            $table->decimal('monthly_cap_points', 12, 2)->nullable()
                ->after('monthly_cap_amount')
                ->comment('Monthly cap expressed in points (NULL = ₹ was used)');
            $table->decimal('min_bill_points', 12, 2)->nullable()
                ->after('min_bill_amount')
                ->comment('Min bill threshold expressed in points (NULL = ₹ was used)');
            $table->decimal('rupees_per_point_at_agreement', 12, 4)->nullable()
                ->after('min_bill_points')
                ->comment('Point valuation locked at time of agreement — never changes retroactively');
        });
    }

    public function down(): void
    {
        Schema::table('partnership_terms', function (Blueprint $table) {
            $table->dropColumn([
                'per_bill_cap_points',
                'monthly_cap_points',
                'min_bill_points',
                'rupees_per_point_at_agreement',
            ]);
        });
    }
};
