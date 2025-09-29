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
        Schema::create('loyalty_settings', function (Blueprint $table) {
             $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->integer('points_per_order')->default(0);
            $table->integer('points_per_referral')->default(0);
            $table->boolean('enable_order_points')->default(false);
            $table->boolean('enable_referral_points')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
