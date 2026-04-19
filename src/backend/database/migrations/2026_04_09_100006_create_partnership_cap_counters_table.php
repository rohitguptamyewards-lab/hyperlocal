<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: RulesEngine
 * Atomic counters for cap enforcement. Separate table enables row-level locking.
 * Race condition strategy (D-004 LOCKED): SELECT FOR UPDATE on this row before any redemption.
 * outlet_id = NULL → partnership-level counter (global monthly cap)
 * outlet_id = ID   → outlet-level counter
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_cap_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('outlet_id')->nullable()->comment('NULL = partnership-level counter');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount_used', 12, 2)->default(0.00);
            $table->unsignedInteger('redemption_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['merchant_id', 'partnership_id', 'outlet_id', 'period_year', 'period_month'],
                'idx_counter_key'
            );
            $table->index(['merchant_id', 'period_year', 'period_month'], 'idx_merchant_period');

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_cap_counters');
    }
};
