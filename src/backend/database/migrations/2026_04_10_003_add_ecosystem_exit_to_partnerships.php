<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership
 * Purpose: E-001 — record when + why a partnership was ecosystem-closed.
 * Additive-only. Nullable columns — only set when status=9 (ECOSYSTEM_INACTIVE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->timestamp('ecosystem_exit_at')
                ->nullable()
                ->after('paused_reason')
                ->comment('E-001: when this partnership was auto-closed due to ecosystem exit');

            $table->string('ecosystem_exit_reason', 500)
                ->nullable()
                ->after('ecosystem_exit_at')
                ->comment('E-001: reason text from the exit event');
        });
    }

    public function down(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropColumn(['ecosystem_exit_at', 'ecosystem_exit_reason']);
        });
    }
};
