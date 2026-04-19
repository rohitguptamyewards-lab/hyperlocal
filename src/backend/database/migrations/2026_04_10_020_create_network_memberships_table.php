<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Network
 * One row per merchant per network.
 * A merchant can be a member of multiple networks (no limit).
 *
 * status: 1=active, 2=suspended
 * Owner merchant is also a member (inserted automatically on network creation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('network_id');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedTinyInteger('status')->default(1)
                ->comment('1=active 2=suspended');
            $table->unsignedBigInteger('invited_by')->nullable()
                ->comment('users.id — null for the founding owner');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['network_id', 'merchant_id'], 'uq_network_membership');

            $table->foreign('network_id')->references('id')->on('hyperlocal_networks')->cascadeOnDelete();
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['merchant_id', 'status'], 'idx_memberships_merchant');
            $table->index(['network_id', 'status'], 'idx_memberships_network');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_memberships');
    }
};
