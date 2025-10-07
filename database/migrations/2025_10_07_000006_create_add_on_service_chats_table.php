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
        Schema::create('add_on_service_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('add_on_service_id');
            $table->unsignedBigInteger('sender_id'); // user_id of sender
            $table->enum('sender_type', ['seller', 'agent']); // who sent the message
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->foreign('add_on_service_id')->references('id')->on('add_on_services')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_on_service_chats');
    }
};
