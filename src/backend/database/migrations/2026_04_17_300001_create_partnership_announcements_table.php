<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Messaging (GAP 3)
 * Stores auto-generated partnership announcements sent to a merchant's customer base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->text('message_template');
            $table->string('status', 20)->default('draft')->comment('draft|approved|sent');
            $table->unsignedInteger('audience_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['partnership_id', 'merchant_id'], 'idx_announcement_partnership_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_announcements');
    }
};
