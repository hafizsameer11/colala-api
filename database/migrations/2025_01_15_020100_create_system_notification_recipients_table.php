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
        Schema::create('system_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('system_push_notifications')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('delivery_status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('device_token')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_notification_recipients');
    }
};
