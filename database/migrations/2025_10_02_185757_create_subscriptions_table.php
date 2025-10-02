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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active','expired','cancelled'])->default('active');

            $table->enum('payment_method', ['wallet','flutterwave','paystack'])->nullable();
            $table->enum('payment_status', ['pending','paid','failed'])->default('pending');
            $table->string('transaction_ref')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
