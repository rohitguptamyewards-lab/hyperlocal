<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Brand A publishes their offer to a network for any member to display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_offer_network_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('partner_offers')->cascadeOnDelete();
            $table->foreignId('network_id')->constrained('hyperlocal_networks')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->unique(['offer_id', 'network_id'], 'idx_offer_network_unique');
            $table->index(['network_id', 'is_active'], 'idx_publication_network');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_offer_network_publications');
    }
};
