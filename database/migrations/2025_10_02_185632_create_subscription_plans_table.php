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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // e.g., Basic, Standard, Ultra
            $table->decimal('price', 12, 2);     // ₦0, ₦5000, ₦10000
            $table->string('currency')->default('NGN');
            $table->integer('duration_days');    // e.g., 30 days
            $table->json('features')->nullable();// store features/benefits in JSON
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
