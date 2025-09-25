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
        Schema::create('support_tickets', function (Blueprint $table) {
           $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('category')->default('technical'); // payment, order, technical, dispute
    $table->string('subject')->nullable();
    $table->text('description')->nullable();
    $table->enum('status',['open','pending','resolved','closed'])->default('open');
    $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('store_order_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
