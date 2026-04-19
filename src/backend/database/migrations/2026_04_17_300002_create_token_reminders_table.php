<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Messaging (GAP 4 + GAP 5)
 * Tracks token expiry reminders and unredeemed-token follow-ups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('partner_claims')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('reminder_type', 30)->comment('expiry_warning|expired_followup');
            $table->text('message');
            $table->string('status', 20)->default('pending')->comment('pending|sent|skipped');
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status'], 'idx_reminder_merchant_status');
            $table->index(['scheduled_for', 'status'], 'idx_reminder_scheduled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_reminders');
    }
};
