<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::create('store_delivery_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('state')->nullable();
            $table->string('local_government');
            $table->enum('variant', ['light','medium','heavy']);
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_free')->default(false);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('store_delivery_pricing');
    }
};
