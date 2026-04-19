<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership
 * Adds granular per-participant permission flags.
 *
 * These three flags replace the coarse suspended_at column and give each
 * merchant independent control over what they allow for a given partnership.
 *
 * Flags (all default TRUE — opt-out model):
 *
 *   issuing_enabled    — this merchant's cashiers can issue claim tokens,
 *                        sending their customers to the partner.
 *
 *   redemption_enabled — this merchant's outlets accept/redeem tokens
 *                        presented by the partner's customers.
 *
 *   campaigns_enabled  — the partner merchant may send campaigns/offers
 *                        to this merchant's customer database.
 *                        (Future: enforced by Campaign + Discovery modules)
 *
 * The master on/off is still partnership.status (LIVE=5 / PAUSED=6).
 * These flags operate within a LIVE partnership to give asymmetric control.
 *
 * Reversible: down() drops all three columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->boolean('issuing_enabled')->default(true)->after('suspension_reason');
            $table->boolean('redemption_enabled')->default(true)->after('issuing_enabled');
            $table->boolean('campaigns_enabled')->default(true)->after('redemption_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->dropColumn(['issuing_enabled', 'redemption_enabled', 'campaigns_enabled']);
        });
    }
};
