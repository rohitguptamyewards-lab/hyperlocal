<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Customer
 * Tracks CSV upload jobs — each row is one file the merchant submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('pending')->comment('pending|processing|completed|failed');
            $table->text('errors_json')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status'], 'idx_customer_upload_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_uploads');
    }
};
