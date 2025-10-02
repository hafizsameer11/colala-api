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
        Schema::create('coupons', function (Blueprint $table) {
           $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->string('code')->unique();        // e.g., PROMO10
            $table->unsignedTinyInteger('discount_type')->default(1); 
            // 1 = percentage, 2 = fixed amount (expand later if needed)
            
            $table->decimal('discount_value', 10, 2); // e.g., 10 = 10% or ₦10 depending on type
            $table->integer('max_usage')->default(1); // total allowed usage
            $table->integer('usage_per_user')->default(1); // per user
            $table->integer('times_used')->default(0);
            $table->date('expiry_date')->nullable();

            $table->enum('status',['active','inactive','expired'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
