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
        Schema::create('seller_help_requests', function (Blueprint $table) {
            $table->id();
            $table->string('service_type'); // store_setup, profile_media, business_docs, store_config, complete_setup, custom
            $table->decimal('fee', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('full_name')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_help_requests');
    }
};
