<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Execution (GAP 10 — Link-Based Token Flow)
 * Stores short shareable codes that map to a partnership UUID.
 * Clicking the link takes the customer to a branded landing page
 * before redirecting them to the existing /claim/{uuid} flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shareable_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique()->comment('8-char uppercase alphanumeric share token');
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->foreignId('created_by_merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->index('partnership_id', 'idx_shareable_links_partnership');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareable_links');
    }
};
