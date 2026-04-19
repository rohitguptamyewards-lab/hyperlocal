<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: EventTriggers
 * Merchant's configured event connections — each source gets an API key.
 * source_type: website | api | shopify | woocommerce | ewrds
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('source_type', 30)->comment('website|api|shopify|woocommerce|ewrds');
            $table->string('merchant_key', 64)->unique()->comment('Public key for trigger URLs');
            $table->string('merchant_secret', 64)->comment('Secret for signed requests');
            $table->json('config')->nullable()->comment('Source-specific config (shop URL, etc.)');
            $table->tinyInteger('status')->default(1)->comment('1=active 2=paused 3=disconnected');
            $table->boolean('test_mode')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['merchant_id', 'status'], 'idx_event_source_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sources');
    }
};
