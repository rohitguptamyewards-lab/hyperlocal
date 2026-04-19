<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Core offer table — a merchant creates offers that can be shown on partner bills.
 * coupon_code: static code (same for all customers, e.g. BREW20OFF)
 * discount_type: 1=percentage 2=flat amount
 * display_template: 'simple' | 'scratch' | 'carousel' — how the offer renders on the bill page
 * status: 1=active 2=inactive
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('coupon_code', 50);
            $table->tinyInteger('discount_type')->default(1)->comment('1=percentage 2=flat');
            $table->decimal('discount_value', 10, 2);
            $table->string('image_url', 500)->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('display_template', 30)->default('simple');
            $table->tinyInteger('status')->default(1)->comment('1=active 2=inactive');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'status', 'deleted_at'], 'idx_offers_merchant_status');
            $table->index(['expiry_date', 'status'], 'idx_offers_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_offers');
    }
};
