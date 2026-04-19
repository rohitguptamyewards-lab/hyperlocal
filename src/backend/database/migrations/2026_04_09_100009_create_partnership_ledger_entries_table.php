<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Ledger
 * Virtual accounting trail. One entry per redemption event.
 * entry_type: 1=debit 2=credit 3=reversal
 * Immutable after creation — no soft deletes, no updated_by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('redemption_id')->nullable();
            $table->tinyInteger('entry_type')->comment('1=debit 2=credit 3=reversal');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('source_merchant_id');
            $table->unsignedBigInteger('target_merchant_id');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedBigInteger('statement_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->index(['merchant_id', 'period_year', 'period_month'], 'idx_ledger_merchant_period');
            $table->index(['partnership_id', 'period_year', 'period_month'], 'idx_ledger_partnership_period');
            $table->index('statement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_ledger_entries');
    }
};
