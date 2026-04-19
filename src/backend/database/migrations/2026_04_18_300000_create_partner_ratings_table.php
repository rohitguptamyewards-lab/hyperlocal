<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Network (GAP 7 — Partner Rating / Trust System)
 * Stores ratings that one merchant gives to another after a live/paused partnership.
 * A merchant can rate their partner once per partnership (unique on partnership_id + rated_by_merchant_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->foreignId('rated_by_merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('rated_merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->tinyInteger('rating')->comment('1–5');
            $table->text('review_text')->nullable();
            $table->timestamps();

            // One rating per rater per partnership
            $table->unique(['partnership_id', 'rated_by_merchant_id'], 'uq_rating_partnership_rater');

            $table->index('rated_merchant_id', 'idx_ratings_rated_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_ratings');
    }
};
