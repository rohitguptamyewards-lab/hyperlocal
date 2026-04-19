<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: EwardsRequest
 * Tracks merchant-initiated requests for eWards integration activation.
 *
 * Flow: merchant submits (pending) → super admin approves/rejects.
 * On approval: EwardsRequestService upserts merchant_integrations row.
 *
 * Soft-deleted: rejected requests are soft-deleted when merchant re-applies,
 * preserving history without blocking a new active request.
 *
 * Service-layer rule: only one non-deleted pending/approved row per merchant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ewards_integration_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('requested_by')
                ->comment('users.id — merchant admin who submitted the request');
            $table->text('notes')->nullable()
                ->comment('Merchant-provided justification or context');
            $table->unsignedBigInteger('reviewed_by')->nullable()
                ->comment('super_admins.id');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('super_admins')->nullOnDelete();

            $table->index(['merchant_id', 'status', 'deleted_at'], 'idx_ewards_req_merchant_status');
            $table->index(['status', 'created_at'], 'idx_ewards_req_queue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewards_integration_requests');
    }
};
