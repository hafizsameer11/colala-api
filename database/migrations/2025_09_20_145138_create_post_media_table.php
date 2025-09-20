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
        Schema::create('post_media', function (Blueprint $t) {
    $t->id();
    $t->foreignId('post_id')->constrained()->cascadeOnDelete();
    $t->string('path');
    $t->enum('type',['image','video']);
    $t->unsignedInteger('position')->default(0);
    $t->timestamps();

    $t->index(['post_id','position']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
