<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: CustomerActivation
 * Customer claim tokens. High-volume — partition at scale.
 * status: 1=issued 2=redeemed 3=expired 4=cancelled
 * token: short alphanumeric (D-002 LOCKED) e.g. HLP-4X9K
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id')->comment('Redeeming merchant');
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('source_outlet_id')->comment('Where QR was scanned');
            $table->unsignedBigInteger('target_outlet_id')->comment('Where benefit is redeemed');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('NULL if anonymous at claim time');
            $table->string('customer_phone', 20)->nullable()->comment('For WhatsApp delivery');
            $table->string('token', 20)->unique()->comment('Short alphanumeric claim code');
            $table->tinyInteger('status')->default(1)->comment('1=issued 2=redeemed 3=expired 4=cancelled');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->index(['merchant_id', 'status', 'deleted_at'], 'idx_claims_merchant_status');
            $table->index(['partnership_id', 'status'], 'idx_partnership_status');
            $table->index(['customer_id', 'status'], 'idx_customer');
            $table->index(['expires_at', 'status'], 'idx_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_claims');
    }
};
