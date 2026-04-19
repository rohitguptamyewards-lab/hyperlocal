<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Execution / WhatsApp Delivery (GAP 2)
 * Adds WhatsApp delivery tracking columns to partner_claims.
 *
 * wa_message_id        — WhatsApp message ID returned by the API on send
 * delivery_status      — current delivery state of the token message
 * delivery_status_updated_at — last time the status was updated (webhook callback)
 * fallback_sms_sent    — true when SMS fallback was triggered
 * fallback_sms_at      — timestamp of fallback SMS dispatch
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_claims', function (Blueprint $table) {
            $table->string('wa_message_id')->nullable()->after('token');
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'read', 'failed', 'no_whatsapp'])
                  ->default('pending')
                  ->after('wa_message_id');
            $table->timestamp('delivery_status_updated_at')->nullable()->after('delivery_status');
            $table->boolean('fallback_sms_sent')->default(false)->after('delivery_status_updated_at');
            $table->timestamp('fallback_sms_at')->nullable()->after('fallback_sms_sent');

            $table->index(['delivery_status'], 'idx_claims_delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('partner_claims', function (Blueprint $table) {
            $table->dropIndex('idx_claims_delivery_status');
            $table->dropColumn([
                'wa_message_id',
                'delivery_status',
                'delivery_status_updated_at',
                'fallback_sms_sent',
                'fallback_sms_at',
            ]);
        });
    }
};
