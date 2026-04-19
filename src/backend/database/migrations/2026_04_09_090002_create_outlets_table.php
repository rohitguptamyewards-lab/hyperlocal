<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual outlet belonging to a merchant.
 * A merchant may have many outlets (national chains, multiple cities).
 * partnership_participants.outlet_id references this table.
 * latitude/longitude stored for future geo-proximity matching (Discovery module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->string('name', 255);
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('merchant_id')->references('id')->on('merchants')->restrictOnDelete();

            $table->index(['merchant_id', 'is_active', 'deleted_at'], 'idx_merchant_active');
            $table->index(['pincode', 'is_active'], 'idx_outlets_pincode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
