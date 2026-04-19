<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Campaign
 * Merchant-created broadcast campaigns via standardised WhatsApp templates.
 * V1 constraint: template_key must be in CampaignTemplate::VALID_KEYS — no custom messages.
 *
 * status: 1=draft 2=scheduled 3=running 4=completed 5=cancelled
 * target_segment: JSON filter (e.g. {"last_seen_days": 30, "merchant_id": 5})
 * template_vars: JSON key-value pairs to fill template placeholders
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->string('name', 200);
            $table->string('template_key', 50)
                ->comment('Fixed list — see CampaignTemplate constants. No custom bodies allowed in V1.');
            $table->json('target_segment')->nullable()
                ->comment('Segment filter: {"last_seen_days": 30} etc.');
            $table->json('template_vars')->nullable()
                ->comment('Merchant-provided values for template variables');
            $table->tinyInteger('status')->default(1)
                ->comment('1=draft 2=scheduled 3=running 4=completed 5=cancelled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'status'], 'idx_campaign_merchant_status');
            $table->index(['scheduled_at', 'status'], 'idx_campaign_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
