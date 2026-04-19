<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Member
 * Own customer identity table — phone number is the primary key.
 * Replaces reliance on eWards or any external system for customer identity.
 * whatsapp_opt_in: must be checked before every send (GDPR / TRAI compliance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('phone', 20)->unique()->comment('Normalised: digits + country code, no spaces');
            $table->string('name', 150)->nullable();
            $table->string('email', 200)->nullable();
            $table->boolean('whatsapp_opt_in')->default(true)->comment('Must be false before sending any WhatsApp');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('phone', 'idx_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
