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
        Schema::create('product_delivery_pricing', function (Blueprint $table) {
             $table->id();
    $table->foreignId('product_id')->constrained()->onDelete('cascade');
    $table->foreignId('delivery_pricing_id')->constrained('store_delivery_pricing')->onDelete('cascade');
    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_delivery_pricing');
    }
};
