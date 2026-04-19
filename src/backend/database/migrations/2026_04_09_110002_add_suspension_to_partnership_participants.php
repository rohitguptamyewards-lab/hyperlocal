<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership
 * Adds per-participant side-suspension columns.
 *
 * Asymmetric pause semantics (D-012 LOCKED):
 *   suspended_at = NULL       → this side is active
 *   suspended_at = timestamp  → this side is suspended
 *
 * When the SOURCE participant is suspended: claim issuance is blocked.
 * When the TARGET participant is suspended: redemption is blocked.
 * The partnership itself stays LIVE (status=5); the other side is unaffected.
 *
 * A full bilateral "Pause" (status=6) is a separate state-machine transition.
 *
 * Reversible: down() drops both columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('approved_at');
            $table->string('suspension_reason', 255)->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('partnership_participants', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
