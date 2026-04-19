<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Customer
 * Merchant's own customer list — populated via CSV upload, token claims, or manual entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('source', 20)->default('upload')->comment('upload|token_claim|manual');
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'phone'], 'uq_merchant_customer_phone');
            $table->index('merchant_id', 'idx_merchant_customer_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_customers');
    }
};
