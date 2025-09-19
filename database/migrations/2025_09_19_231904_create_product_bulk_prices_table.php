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
        Schema::create('product_bulk_prices', function (Blueprint $table) {
              $table->id();
        $table->unsignedBigInteger('product_id')->nullable();
        $table->integer('min_quantity')->nullable();
        $table->decimal('amount', 12, 2)->nullable();
        $table->decimal('discount_percent', 5, 2)->nullable();
        $table->timestamps();

        $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_bulk_prices');
    }
};
