<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Execution / Token Expiry Reminders (GAP 4)
 * Stores per-merchant reminder configuration in a dedicated table.
 *
 * merchant_id         — FK to merchants, unique (one row per merchant)
 * reminder_enabled    — master toggle for sending expiry reminders
 * remind_hours_before — how many hours before expiry to send the reminder
 * message_template    — optional custom message body (NULL = use system default)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')
                  ->unique()
                  ->constrained('merchants')
                  ->cascadeOnDelete();
            $table->boolean('reminder_enabled')->default(false);
            $table->integer('remind_hours_before')->default(12);
            $table->text('message_template')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_reminder_settings');
    }
};
