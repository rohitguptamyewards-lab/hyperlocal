<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: RulesEngine
 * Advanced rules and conditions per partnership.
 * All JSON columns use MySQL 8 native JSON type.
 *
 * customer_type_rules JSON structure:
 *   {"new": {"cap_multiplier": 1.5}, "existing": {"cap_multiplier": 0.5}, "reactivated": {"cap_multiplier": 1.2}}
 *
 * blackout_rules JSON structure:
 *   [{"type": "date", "value": "2026-12-25"}, {"type": "weekday", "value": [6, 7]}]
 *
 * time_band_rules JSON structure:
 *   [{"days": [1,2,3,4,5], "from": "09:00", "to": "17:00"}]
 *
 * stacking_rules JSON structure:
 *   {"allow_stacking": false, "blocked_offer_types": [1, 2]}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_id')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->json('customer_type_rules')->nullable();
            $table->unsignedInteger('inactivity_days')->default(90)->comment('Days until customer classified as reactivated');
            $table->json('blackout_rules')->nullable();
            $table->json('time_band_rules')->nullable();
            $table->json('stacking_rules')->nullable();
            $table->unsignedInteger('uses_per_customer')->nullable()->comment('NULL = unlimited');
            $table->unsignedInteger('cooling_period_days')->nullable()->comment('Days between uses per customer');
            $table->boolean('first_time_only')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->index(['merchant_id', 'deleted_at'], 'idx_rules_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_rules');
    }
};
