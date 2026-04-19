<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Analytics
 * One row per first-visit redemption (customer_type = NEW).
 * Retention flags are set retroactively by the UpdateRetentionFlags scheduled job.
 *
 * customer_type: 1=new 2=existing 3=reactivated (mirrors partner_redemptions)
 * retained_30d/60d/90d: set to true when customer makes another redemption
 *   at the target merchant within 30/60/90 days of attributed_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_attributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('redemption_id')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('source_merchant_id');
            $table->unsignedBigInteger('target_merchant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->tinyInteger('customer_type');
            $table->decimal('benefit_amount', 12, 2);
            $table->timestamp('attributed_at');
            $table->date('period_month');
            $table->boolean('retained_30d')->default(false);
            $table->boolean('retained_60d')->default(false);
            $table->boolean('retained_90d')->default(false);
            $table->timestamp('retained_30d_at')->nullable();
            $table->timestamp('retained_60d_at')->nullable();
            $table->timestamp('retained_90d_at')->nullable();
            $table->timestamps();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();
            $table->foreign('redemption_id')->references('id')->on('partner_redemptions')->restrictOnDelete();

            $table->index(['partnership_id', 'period_month'], 'idx_attributions_partnership_period');
            $table->index(['customer_id', 'attributed_at'], 'idx_customer_date');
            $table->index(['target_merchant_id', 'attributed_at'], 'idx_target_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_attributions');
    }
};
