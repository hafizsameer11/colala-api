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
        Schema::create('dispute_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->nullable()->constrained()->cascadeOnDelete(); // Nullable initially, set after dispute creation
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete(); // Buyer user ID
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete(); // Seller user ID (store owner)
            $table->foreignId('store_id')->constrained()->cascadeOnDelete(); // Store ID
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispute_chats');
    }
};
