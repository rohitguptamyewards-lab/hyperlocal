<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: WhatsAppCredit
 * Immutable append-only ledger — every credit movement is one row.
 * Never UPDATE rows in this table. Inserts only.
 *
 * entry_type:
 *   allocation   — super admin grants credits to merchant
 *   consumption  — a WhatsApp message was sent (credits_delta is negative)
 *   reversal     — a failed/bounced send is reversed (credits_delta is positive)
 *   adjustment   — manual correction by super admin
 *
 * balance_after is denormalized for audit readability — always equals
 * the merchant_whatsapp_balance.balance at time of insert.
 *
 * reference_type / reference_id: polymorphic pointer to the row that
 * caused this ledger entry (e.g. 'partner_claim' → claim_id for a token delivery).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->enum('entry_type', ['allocation', 'consumption', 'reversal', 'adjustment']);
            $table->integer('credits_delta')->comment('Positive = add, negative = deduct');
            $table->unsignedInteger('balance_after')->comment('Denormalized running balance snapshot');
            $table->string('reference_type', 50)->nullable()
                ->comment('partner_claim | campaign_send | approval_code | manual');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('allocated_by')->nullable()
                ->comment('super_admins.id for allocations; null for auto-consumption');
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — immutable rows

            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->foreign('allocated_by')->references('id')->on('super_admins')->nullOnDelete();

            $table->index(['merchant_id', 'created_at'], 'idx_wa_ledger_merchant_date');
            $table->index(['merchant_id', 'entry_type'], 'idx_wa_ledger_merchant_type');
            $table->index(['reference_type', 'reference_id'], 'idx_wa_ledger_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_credit_ledger');
    }
};
