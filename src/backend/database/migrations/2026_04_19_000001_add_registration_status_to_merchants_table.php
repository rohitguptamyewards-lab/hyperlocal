<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->enum('registration_status', ['pending', 'approved', 'rejected'])
                  ->default('approved')
                  ->after('is_active');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('registration_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
        });

        // Self-registered brands are inactive (is_active = false) — mark them pending
        DB::table('merchants')
            ->where('is_active', false)
            ->update(['registration_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['registration_status', 'reviewed_by', 'reviewed_at', 'rejection_reason']);
        });
    }
};
