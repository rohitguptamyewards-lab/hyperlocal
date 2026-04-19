<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Enablement
 * Staff continuity layer — one row per outlet per partnership.
 * Dormancy detection job sets is_dormant=true when last_used_at exceeds threshold.
 * dormancy_alert_sent prevents repeat alerts per dormancy cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_staff_enablement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('outlet_id');
            $table->timestamp('last_training_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_dormant')->default(false);
            $table->timestamp('dormant_since')->nullable();
            $table->boolean('dormancy_alert_sent')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->unique(['merchant_id', 'partnership_id', 'outlet_id'], 'idx_outlet_key');
            $table->index(['is_dormant', 'dormancy_alert_sent'], 'idx_dormant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_staff_enablement');
    }
};
