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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
        $table->unsignedBigInteger('store_id');
        $table->unsignedBigInteger('category_id');
        $table->string('name');
        $table->string('short_description')->nullable();
        $table->text('full_description')->nullable();
        $table->decimal('price_from', 10, 2)->nullable();
        $table->decimal('price_to', 10, 2)->nullable();
        $table->decimal('discount_price', 10, 2)->nullable();
        $table->string('status')->default('active'); // active, inactive, draft
        $table->timestamps();

        $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        $table->foreign('category_id')->references('id')->on('categories');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
