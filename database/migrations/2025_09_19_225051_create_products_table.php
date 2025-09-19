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
        Schema::create('products', function (Blueprint $table) {
           $table->id();
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->unsignedBigInteger('category_id')->nullable();
    $table->string('brand')->nullable();
    $table->text('description')->nullable();
    $table->decimal('price', 12, 2)->nullable();
    $table->decimal('discount_price', 12, 2)->nullable();
    $table->boolean('has_variants')->default(false);
    $table->string('video')->nullable();
    $table->enum('status', ['draft','active','inactive'])->default('draft');
    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
