<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Network
 * Pending invitations to join a network.
 * Accepted invitations create a network_memberships row.
 *
 * invite_channel: email | whatsapp | link
 * contact: email address or phone number (null for generic "copy link" invites)
 * token: unique random token used in the /join/{token} public URL
 * merchant_id: filled when the invitation is accepted
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('network_id');
            $table->unsignedBigInteger('invited_by');
            $table->enum('invite_channel', ['email', 'whatsapp', 'link'])->default('link');
            $table->string('contact')->nullable()
                ->comment('Email or phone of the invitee; null for generic link invites');
            $table->string('token', 64)->unique()
                ->comment('Secure random token — used in /join/{token} public URL');
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('merchant_id')->nullable()
                ->comment('Filled when invitation is accepted');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('network_id')->references('id')->on('hyperlocal_networks')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('merchant_id')->references('id')->on('merchants')->nullOnDelete();

            $table->index('token', 'idx_invitations_token');
            $table->index(['network_id', 'status'], 'idx_invitations_network_status');
            $table->index('contact', 'idx_invitations_contact');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_invitations');
    }
};
