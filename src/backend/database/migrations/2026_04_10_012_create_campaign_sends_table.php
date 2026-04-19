<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Campaign
 * One row per campaign per member — idempotent send tracking.
 * status: 1=pending 2=sent 3=failed 4=delivered
 * Unique constraint on (campaign_id, member_id) prevents duplicate sends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('member_id');
            $table->tinyInteger('status')->default(1)
                ->comment('1=pending 2=sent 3=failed 4=delivered');
            $table->timestamp('sent_at')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->unique(['campaign_id', 'member_id'], 'idx_campaign_send_unique');
            $table->index(['campaign_id', 'status'], 'idx_campaign_send_status');
            $table->index(['status', 'sent_at'], 'idx_send_status_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_sends');
    }
};
