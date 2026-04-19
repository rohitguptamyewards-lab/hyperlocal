<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: IntegrationHub
 * One row per merchant per provider.
 * is_loyalty_source: exactly ONE provider per merchant should be true.
 *   Enforced at service layer (not DB) — MySQL partial unique not natively supported.
 * config: encrypted JSON; contains API keys, endpoint URLs, etc.
 *   DO NOT store plaintext credentials — encrypt before write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('provider', 50)->comment('ewrds|capillary|pos_xyz|generic_pos');
            $table->json('config')->nullable()->comment('Encrypted credentials — use IntegrationResolverService to read');
            $table->boolean('is_loyalty_source')->default(false)
                ->comment('True = use this provider as loyalty balance source. Max one per merchant.');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->unique(['merchant_id', 'provider'], 'idx_merchant_provider');
            $table->index(['merchant_id', 'is_active'], 'idx_integrations_merchant_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_integrations');
    }
};
