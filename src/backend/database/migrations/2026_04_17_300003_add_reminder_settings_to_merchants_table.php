<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Messaging (GAP 4 + GAP 5)
 * Adds per-merchant reminder configuration columns for token expiry and follow-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->boolean('expiry_reminder_enabled')->default(true)->after('ecosystem_active');
            $table->unsignedSmallInteger('expiry_reminder_hours')->default(6)->after('expiry_reminder_enabled');
            $table->boolean('followup_enabled')->default(true)->after('expiry_reminder_hours');
            $table->unsignedSmallInteger('followup_hours_after_expiry')->default(2)->after('followup_enabled');
            $table->unsignedSmallInteger('followup_extension_hours')->default(72)->after('followup_hours_after_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'expiry_reminder_enabled',
                'expiry_reminder_hours',
                'followup_enabled',
                'followup_hours_after_expiry',
                'followup_extension_hours',
            ]);
        });
    }
};
