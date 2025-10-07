<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // referrer user id
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->bigInteger('amount')->default(0); // in naira
            $table->timestamps();
            $table->index(['user_id','store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_referral_earnings');
    }
};


