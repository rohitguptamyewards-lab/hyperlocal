<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership (GAP 3 — Auto Partnership Announcement)
 * Stores merchant-authored announcements about a partnership sent to their own customer base.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The table may already exist from an earlier migration draft; drop first to avoid
        // conflicts with the new schema.
        Schema::dropIfExists('partnership_announcements');

        Schema::create('partnership_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->unsignedBigInteger('sent_by_merchant_id');
            $table->foreign('sent_by_merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->text('message_text');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->enum('status', ['draft', 'sent', 'scheduled'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['partnership_id', 'sent_by_merchant_id'], 'idx_pa_partnership_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_announcements');
    }
};
