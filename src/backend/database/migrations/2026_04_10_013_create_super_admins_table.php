<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: SuperAdmin
 * Separate auth context from merchant users — no merchant_id, no role column.
 * Sanctum tokens for this model are stored in personal_access_tokens
 * with tokenable_type = 'App\Models\SuperAdmin'.
 * Guard: 'super-admin' (registered in config/auth.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('email', 'idx_super_admins_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admins');
    }
};
