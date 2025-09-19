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
        Schema::create('product_images', function (Blueprint $table) {
    
      $table->id();
               $table->foreignId('product_id')->constrained()->onDelete('cascade');
    $table->string('sku')->nullable();
    $table->string('color')->nullable();
    $table->string('size')->nullable();
    $table->decimal('price', 12, 2);
    $table->decimal('discount_price', 12, 2)->nullable();
    $table->integer('stock')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
