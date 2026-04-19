<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: LoyaltyBridge
 * Local balance cache per member per merchant.
 * This is the source of truth at runtime — pulled from external on first contact,
 * updated on every earn/redeem, pushed back to external provider after each change.
 *
 * currency_type: 'points' | 'cashback' | 'stamps'
 * provider: which system was last synced ('local' = only tracked internally)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_loyalty_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('merchant_id');
            $table->decimal('balance', 14, 4)->default(0);
            $table->string('currency_type', 20)->default('points')
                ->comment('points|cashback|stamps');
            $table->string('provider', 50)->default('local')
                ->comment('Last sync source — local|ewrds|capillary|pos_xyz');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->unique(['member_id', 'merchant_id'], 'idx_member_merchant_balance');
            $table->index('merchant_id', 'idx_balance_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_loyalty_balances');
    }
};
