<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: RulesEngine / Execution
 * Purpose: Deduplicating rule snapshot storage (D-003 LOCKED 2026-04-10).
 *
 * At 100M redemptions, storing a 2KB JSON snapshot per row wastes ~200GB.
 * This table stores each unique rule configuration ONCE.
 * partner_redemptions.rule_version_id FK points here.
 *
 * A new row is created whenever the terms or rules for a partnership change
 * (version increment on partnership_terms or partnership_rules).
 * Rows are IMMUTABLE after creation — never updated, never soft-deleted.
 *
 * Integration points:
 *   - Written by: Execution\Services\RedemptionService::resolveRuleVersion()
 *   - Read by:    Execution (dispute resolution), Analytics, Ledger
 *   - DO NOT modify columns without updating RedemptionService and any dispute tooling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedInteger('terms_version')->default(1);
            $table->unsignedInteger('rules_version')->default(1);
            $table->json('terms_snapshot')->nullable()->comment('Snapshot of partnership_terms at this version');
            $table->json('rules_snapshot')->nullable()->comment('Snapshot of partnership_rules at this version');
            $table->timestamp('effective_from')->comment('When this version became active');
            $table->timestamp('created_at')->useCurrent();

            // Uniqueness: one row per partnership per terms+rules version combination
            $table->unique(['partnership_id', 'terms_version', 'rules_version'], 'idx_version_key');
            $table->index('partnership_id', 'idx_ruleversions_partnership');

            $table->foreign('partnership_id')
                ->references('id')
                ->on('partnerships')
                ->restrictOnDelete();
        });

        // Add rule_version_id FK column to partner_redemptions (additive — nullable)
        // Existing rows keep rule_snapshot JSON; new rows will also set this FK.
        Schema::table('partner_redemptions', function (Blueprint $table) {
            $table->unsignedBigInteger('rule_version_id')
                ->nullable()
                ->after('rule_snapshot')
                ->comment('FK to partnership_rule_versions — null for rows created before D-003');

            $table->index('rule_version_id', 'idx_rule_version');
        });
    }

    public function down(): void
    {
        Schema::table('partner_redemptions', function (Blueprint $table) {
            $table->dropIndex('idx_rule_version');
            $table->dropColumn('rule_version_id');
        });

        Schema::dropIfExists('partnership_rule_versions');
    }
};
