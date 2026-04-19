<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Execution
 * Executed benefit usage. Immutable after creation — no soft deletes.
 * rule_snapshot: exact rules JSON at time of execution (D-003 LOCKED: JSON column).
 * customer_type: 1=new 2=existing 3=reactivated
 * status: 1=completed 2=reversed 3=disputed
 * transaction_id: idempotency key from POS/bill reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('claim_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('bill_id', 100)->nullable()->comment('External bill reference');
            $table->string('transaction_id', 100)->nullable()->comment('Idempotency key');
            $table->decimal('bill_amount', 12, 2);
            $table->decimal('benefit_amount', 12, 2);
            $table->tinyInteger('customer_type')->comment('1=new 2=existing 3=reactivated');
            $table->json('rule_snapshot')->comment('Exact rules in force at execution time');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->tinyInteger('approval_method')->nullable()->comment('1=auto 2=manager 3=otp');
            $table->tinyInteger('status')->default(1)->comment('1=completed 2=reversed 3=disputed');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversed_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();
            $table->foreign('claim_id')->references('id')->on('partner_claims')->restrictOnDelete();

            $table->unique(['merchant_id', 'transaction_id'], 'idx_idempotency');
            $table->index(['merchant_id', 'partnership_id', 'created_at'], 'idx_merchant_partnership');
            $table->index(['customer_id', 'customer_type'], 'idx_customer_type');
            $table->index(['outlet_id', 'created_at'], 'idx_outlet_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_redemptions');
    }
};
