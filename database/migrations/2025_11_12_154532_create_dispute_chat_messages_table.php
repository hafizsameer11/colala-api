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
        Schema::create('dispute_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->enum('sender_type', ['buyer', 'seller', 'admin']); // Who sent the message
            $table->text('message')->nullable();
            $table->string('image')->nullable(); // Image path if message has image
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispute_chat_messages');
    }
};
