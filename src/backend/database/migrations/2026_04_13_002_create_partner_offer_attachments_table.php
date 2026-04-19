<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: PartnerOffers
 * Links an offer to a partnership. Brand B attaches Brand A's offer to show on Brand B's bills.
 * attached_by_merchant_id: the merchant choosing to SHOW this offer (Brand B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_offer_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('partner_offers')->cascadeOnDelete();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->unsignedBigInteger('attached_by_merchant_id');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->unique(['offer_id', 'partnership_id'], 'idx_offer_partnership_unique');
            $table->index(['partnership_id', 'is_active'], 'idx_attachment_partnership');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_offer_attachments');
    }
};
