<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: EventTriggers
 * Full event log — every received event with raw payload, normalized form, and outcome.
 * processing_status: received | processing | completed | failed | duplicate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_source_id')->nullable();
            $table->unsignedBigInteger('merchant_id');
            $table->string('idempotency_key', 200)->comment('order_id + event_type + merchant_id');
            $table->string('event_type', 50);
            $table->json('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->unsignedBigInteger('member_id')->nullable()->comment('Resolved member');
            $table->string('processing_status', 20)->default('received');
            $table->json('action_outcome')->nullable();
            $table->text('error_reason')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key', 'idx_event_log_idemp');
            $table->index(['merchant_id', 'received_at'], 'idx_event_log_merchant');
            $table->index(['processing_status', 'received_at'], 'idx_event_log_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_log');
    }
};
