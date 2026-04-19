<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module: Partnership
 * Creates the core partnership record table.
 * scope_type: 1=outlet, 2=brand-wide (outlet_id=NULL in participants)
 * status: 1=SUGGESTED 2=REQUESTED 3=NEGOTIATING 4=AGREED 5=LIVE 6=PAUSED 7=EXPIRED 8=REJECTED
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnerships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->string('name', 255);
            $table->tinyInteger('scope_type')->default(1)->comment('1=outlet 2=brand');
            $table->tinyInteger('status')->default(1)->comment('1=SUGGESTED 2=REQUESTED 3=NEGOTIATING 4=AGREED 5=LIVE 6=PAUSED 7=EXPIRED 8=REJECTED');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('agreement_id')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->string('paused_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'status', 'deleted_at'], 'idx_merchant_status');
            $table->index(['merchant_id', 'start_at', 'end_at'], 'idx_merchant_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnerships');
    }
};
