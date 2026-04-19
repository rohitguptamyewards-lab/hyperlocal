<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Messaging (GAP 2)
 * Tracks delivery status of every WhatsApp/SMS message sent to customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained('partner_claims')->nullOnDelete();
            $table->string('customer_phone', 20);
            $table->string('channel', 20)->comment('whatsapp|sms');
            $table->string('message_type', 30)->comment('token_issued|reminder|announcement|follow_up');
            $table->string('status', 20)->default('queued')->comment('queued|sent|delivered|failed|read');
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status'], 'idx_delivery_merchant_status');
            $table->index(['merchant_id', 'message_type'], 'idx_delivery_merchant_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_delivery_logs');
    }
};
