<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds merchant and role context to the users table.
 * role: 1=admin (brand owner) 2=manager (outlet manager) 3=cashier
 * outlet_id: NULL for admin, set for manager/cashier scoped to a specific outlet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            $table->unsignedBigInteger('outlet_id')->nullable()->after('merchant_id');
            $table->tinyInteger('role')->default(1)->after('outlet_id')->comment('1=admin 2=manager 3=cashier');

            $table->index(['merchant_id', 'role'], 'idx_merchant_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_merchant_role');
            $table->dropColumn(['merchant_id', 'outlet_id', 'role']);
        });
    }
};
