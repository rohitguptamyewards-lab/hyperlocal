<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Campaigns (GAP 6 — Follow-Up for Unredeemed/Expired Tokens)
 * Stores merchant-configured follow-up campaign rules that trigger on token lifecycle events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followup_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('partnership_id')->nullable()->constrained('partnerships')->nullOnDelete();
            $table->enum('trigger_type', ['token_expired_unredeemed'])->default('token_expired_unredeemed');
            $table->unsignedInteger('delay_hours')->default(24);
            $table->text('message_template');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();

            $table->index('merchant_id', 'idx_fc_merchant');
            $table->index(['merchant_id', 'is_active'], 'idx_fc_merchant_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followup_campaigns');
    }
};
