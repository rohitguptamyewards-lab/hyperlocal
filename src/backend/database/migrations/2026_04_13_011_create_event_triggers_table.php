<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: EventTriggers
 * Rules: "when event X happens with conditions Y, execute action Z"
 * condition_json: {"min_amount": 200, "category": "beverages", "first_purchase": true}
 * action_config_json: {"offer_id": 5, "campaign_id": null, "template": "offer_announcement"}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_triggers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('event_source_id')->nullable()->constrained('event_sources')->nullOnDelete();
            $table->string('name', 200);
            $table->string('event_type', 50)->comment('transaction_completed|first_purchase|etc.');
            $table->json('condition_json')->nullable()->comment('Conditions to evaluate');
            $table->string('action_type', 50)->comment('issue_offer|send_whatsapp|make_eligible');
            $table->json('action_config_json')->nullable()->comment('Action-specific config');
            $table->unsignedBigInteger('partnership_id')->nullable();
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->index(['merchant_id', 'event_type', 'is_active'], 'idx_trigger_merchant_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_triggers');
    }
};
