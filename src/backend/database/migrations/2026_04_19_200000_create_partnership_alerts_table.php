<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight in-app alerts for partnership events.
 *
 * Types: offer_filled | partner_accepted | partner_rejected | offer_updated
 *
 * Owner module: Partnership
 * Table: partnership_alerts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->unsignedBigInteger('recipient_merchant_id');  // who should see this
            $table->string('type', 50);                           // offer_filled | partner_accepted | …
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_merchant_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_alerts');
    }
};
