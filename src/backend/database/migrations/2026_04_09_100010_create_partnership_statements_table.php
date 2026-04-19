<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Ledger
 * Merchant-facing monthly settlement and ROI snapshot.
 * status: 1=draft 2=final 3=sent 4=acknowledged
 * Unique per merchant+partnership+period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->tinyInteger('status')->default(1)->comment('1=draft 2=final 3=sent 4=acknowledged');
            $table->unsignedInteger('total_redemptions')->default(0);
            $table->decimal('total_benefit_given', 12, 2)->default(0.00);
            $table->decimal('total_benefit_received', 12, 2)->default(0.00);
            $table->unsignedInteger('new_customers_acquired')->default(0);
            $table->unsignedInteger('reactivated_customers')->default(0);
            $table->unsignedInteger('retained_30d')->default(0);
            $table->unsignedInteger('retained_60d')->default(0);
            $table->unsignedInteger('retained_90d')->default(0);
            $table->decimal('revenue_post_first_visit', 12, 2)->default(0.00);
            $table->decimal('net_contribution', 12, 2)->default(0.00);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->unique(
                ['merchant_id', 'partnership_id', 'period_year', 'period_month'],
                'idx_period_key'
            );
            $table->index(['merchant_id', 'status', 'deleted_at'], 'idx_statements_merchant_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_statements');
    }
};
