<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Network
 * A Hyperlocal Network groups merchants under a common identity.
 * Owner merchant creates the network, invites others.
 * Partnerships within a network carry network_id on the partnerships table.
 *
 * status: 1=active, 2=suspended, 3=closed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hyperlocal_networks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug', 100)->unique()
                ->comment('URL-safe handle, e.g. indiranagar-circle');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('owner_merchant_id');
            $table->unsignedTinyInteger('status')->default(1)
                ->comment('1=active 2=suspended 3=closed');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('owner_merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('owner_merchant_id', 'idx_networks_owner');
            $table->index('slug', 'idx_networks_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hyperlocal_networks');
    }
};
