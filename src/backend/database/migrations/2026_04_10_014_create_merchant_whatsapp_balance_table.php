<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: WhatsAppCredit
 * Denormalized current balance per merchant — O(1) reads.
 * Updated atomically alongside whatsapp_credit_ledger via locked DB transaction.
 * Pattern mirrors cap_counters used by CapEnforcementService.
 *
 * One row per merchant. Created via upsert on first credit allocation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_whatsapp_balance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedInteger('balance')->default(0);
            $table->boolean('low_balance_alerted')->default(false)
                ->comment('Prevents repeated low-balance alert spam');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('merchant_id', 'uq_whatsapp_balance_merchant');
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_whatsapp_balance');
    }
};
