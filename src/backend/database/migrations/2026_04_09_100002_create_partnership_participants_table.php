<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership
 * Who is in the partnership (both sides).
 * Scope rule (D-008 LOCKED):
 *   outlet_id = NULL  → ALL outlets of this merchant (brand-level)
 *   outlet_id = ID    → that specific outlet only
 * role: 1=proposer 2=acceptor
 * approval_status: 1=pending 2=approved 3=rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('outlet_id')->nullable()->comment('NULL = all outlets (brand-level)');
            $table->tinyInteger('role')->comment('1=proposer 2=acceptor');
            $table->tinyInteger('approval_status')->default(1)->comment('1=pending 2=approved 3=rejected');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->index(['partnership_id', 'deleted_at'], 'idx_participants_partnership');
            $table->index(['merchant_id', 'outlet_id', 'deleted_at'], 'idx_merchant_outlet');
            $table->index(['merchant_id', 'role', 'approval_status'], 'idx_participants_merchant_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_participants');
    }
};
