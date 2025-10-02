<?php

// database/migrations/2025_10_02_000004_create_banners_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');   // store uploaded banner
            $table->string('link')->nullable();
            $table->unsignedInteger('impressions')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('banners');
    }
};
