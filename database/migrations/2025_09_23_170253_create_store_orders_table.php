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
         Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('placed'); // placed, packed, out_for_delivery, delivered, funds_released, completed, cancelled
            $table->decimal('shipping_fee', 14, 2)->default(0);
            $table->decimal('items_subtotal', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('subtotal_with_shipping', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_orders');
    }
};
