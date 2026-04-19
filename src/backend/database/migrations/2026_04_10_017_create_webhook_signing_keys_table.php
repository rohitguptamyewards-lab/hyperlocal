<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Webhook
 * HMAC signing keys for inbound webhook verification.
 * Secrets stored encrypted (Laravel encryption — APP_KEY must be set).
 *
 * Multiple active keys per source = zero-downtime key rotation.
 * During rotation: add new key, verify both keys accept, then deactivate old.
 *
 * key_id: a short reference string sent in X-Webhook-Key-ID header so the
 * verifier can pick the correct key without trying all active keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_signing_keys', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->comment('ewards | generic');
            $table->string('key_id', 50)->unique()->comment('Short handle — sent in X-Webhook-Key-ID header');
            $table->text('secret')->comment('Encrypted HMAC secret — cast as encrypted in model');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('super_admins')->nullOnDelete();

            $table->index(['source', 'is_active'], 'idx_webhook_keys_source_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_signing_keys');
    }
};
