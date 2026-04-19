<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_invitations', function (Blueprint $table) {
            $table->unsignedInteger('max_uses')->default(1)->after('status');
            $table->unsignedInteger('uses_count')->default(0)->after('max_uses');
        });
    }

    public function down(): void
    {
        Schema::table('network_invitations', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'uses_count']);
        });
    }
};
