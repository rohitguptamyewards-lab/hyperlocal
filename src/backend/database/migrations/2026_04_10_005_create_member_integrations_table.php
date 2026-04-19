<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Member / IntegrationHub
 * Links our members to their identities on external loyalty/POS providers.
 * One member can have IDs on multiple providers (eWards, Capillary, POS, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->string('provider', 50)->comment('ewrds|capillary|pos_xyz|generic_pos');
            $table->string('external_id', 100)->comment('Member ID on the external system');
            $table->json('meta')->nullable()->comment('Extra data from external system (tier, tags, etc.)');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->unique(['provider', 'external_id'], 'idx_provider_external');
            $table->index(['member_id', 'provider'], 'idx_member_provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_integrations');
    }
};
