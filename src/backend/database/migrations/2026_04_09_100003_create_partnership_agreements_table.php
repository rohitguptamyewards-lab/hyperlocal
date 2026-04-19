<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership
 * Legal/commercial acceptance record per partnership.
 * file_path: S3 path locally or local storage path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('merchant_id');
            $table->string('version', 20);
            $table->string('file_path', 1000)->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partnership_id')->references('id')->on('partnerships')->restrictOnDelete();

            $table->index('partnership_id');
            $table->index(['merchant_id', 'deleted_at'], 'idx_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_agreements');
    }
};
