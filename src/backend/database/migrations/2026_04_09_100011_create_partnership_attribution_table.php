<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Analytics
 * Retention and source tracking. One row per customer per partnership.
 * customer_type_at_entry: 1=new 2=existing 3=reactivated
 * Retention jobs update visit_count_Xd and revenue_Xd at 30/60/90 day marks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_attribution', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('first_visit_redemption_id');
            $table->timestamp('first_visit_at');
            $table->tinyInteger('customer_type_at_entry')->comment('1=new 2=existing 3=reactivated');
            $table->unsignedInteger('visit_count_30d')->default(0);
            $table->unsignedInteger('visit_count_60d')->default(0);
            $table->unsignedInteger('visit_count_90d')->default(0);
            $table->decimal('revenue_30d', 12, 2)->default(0.00);
            $table->decimal('revenue_60d', 12, 2)->default(0.00);
            $table->decimal('revenue_90d', 12, 2)->default(0.00);
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();
            $table->foreign('first_visit_redemption_id')->references('id')->on('partner_redemptions')->restrictOnDelete();

            $table->unique(['merchant_id', 'partnership_id', 'customer_id'], 'idx_customer_key');
            $table->index(['merchant_id', 'partnership_id'], 'idx_attribution_merchant_partnership');
            $table->index(['merchant_id', 'customer_type_at_entry', 'first_visit_at'], 'idx_first_visit_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_attribution');
    }
};
