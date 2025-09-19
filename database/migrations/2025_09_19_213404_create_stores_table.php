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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
             $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('store_name')->nullable();
            $table->string('store_email')->unique();
            $table->string('store_phone')->nullable();
            $table->string('store_location')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('banner_image')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('theme_color')->nullable();
            $table->string('referral_code')->nullable();
            $table->enum('status', ['pending','approved','rejected','suspended'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
