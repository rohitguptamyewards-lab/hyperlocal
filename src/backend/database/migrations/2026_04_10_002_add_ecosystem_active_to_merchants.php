<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership / Migration
 * Purpose: E-001 — ecosystem exit flag (LOCKED 2026-04-10).
 *
 * When a merchant leaves eWards (account suspended / brand offboarded),
 * ecosystem_active is set to false. All their LIVE/PAUSED partnerships
 * are auto-closed to ECOSYSTEM_INACTIVE (status=9) by AutoCloseOnEcosystemExit.
 *
 * Default: true — all existing merchants are in the ecosystem.
 * Additive-only: no existing columns changed.
 *
 * eWards integration: on migration, replace the local admin toggle with
 * a webhook handler that sets this flag when eWards fires the offboarding event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->boolean('ecosystem_active')
                ->default(true)
                ->after('open_to_partnerships')
                ->comment('E-001: false = merchant has left eWards; all partnerships auto-closed');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('ecosystem_active');
        });
    }
};
