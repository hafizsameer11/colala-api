<?php

// database/migrations/2025_10_02_000003_create_announcements_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('message', 200);
            $table->unsignedInteger('impressions')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('announcements');
    }
};
