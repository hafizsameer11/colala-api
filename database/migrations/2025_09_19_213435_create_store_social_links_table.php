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
        Schema::create('store_social_links', function (Blueprint $table) {
           $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['whatsapp','instagram','facebook','twitter','tiktok','linkedin']);
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_social_links');
    }
};
