<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Discovery
 * Pre-computed partner suggestions for merchant onboarding.
 * fit_score: 0.0000 to 1.0000
 * confidence_tier: 1=high 2=medium 3=low
 * status: 1=active 2=dismissed 3=converted
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('recommended_merchant_id');
            $table->decimal('fit_score', 5, 4)->comment('0.0000 to 1.0000');
            $table->string('rationale', 1000)->nullable();
            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->tinyInteger('confidence_tier')->comment('1=high 2=medium 3=low');
            $table->tinyInteger('status')->default(1)->comment('1=active 2=dismissed 3=converted');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('computed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status'], 'idx_merchant_score');
            $table->index(['cluster_id', 'status'], 'idx_cluster');
            $table->index(['expires_at', 'status'], 'idx_rec_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_recommendations');
    }
};
