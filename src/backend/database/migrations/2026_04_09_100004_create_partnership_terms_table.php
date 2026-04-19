<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership (written) / RulesEngine (read)
 * Visible V1 commercial configuration. One row per partnership.
 * approval_mode: 1=auto 2=manager-approval 3=otp
 * version increments each time terms are edited (for audit trail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_id')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->decimal('per_bill_cap_amount', 12, 2)->nullable()->comment('Max benefit per bill in currency');
            $table->decimal('per_bill_cap_percent', 5, 2)->nullable()->comment('Max benefit as % of bill');
            $table->decimal('min_bill_amount', 12, 2)->nullable()->comment('Minimum bill to qualify');
            $table->decimal('monthly_cap_amount', 12, 2)->nullable()->comment('Global monthly ceiling');
            $table->decimal('partner_monthly_cap', 12, 2)->nullable()->comment('Per-partner monthly ceiling');
            $table->decimal('outlet_monthly_cap', 12, 2)->nullable()->comment('Per-outlet monthly ceiling');
            $table->tinyInteger('approval_mode')->default(1)->comment('1=auto 2=manager 3=otp');
            $table->decimal('approval_threshold', 12, 2)->nullable()->comment('Auto-approve below this amount');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->index(['merchant_id', 'deleted_at'], 'idx_terms_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_terms');
    }
};
