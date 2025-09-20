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
       Schema::create('posts', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // author = User
    $t->text('body')->nullable();
    $t->unsignedInteger('likes_count')->default(0);
    $t->unsignedInteger('comments_count')->default(0);
    $t->unsignedInteger('shares_count')->default(0);
    $t->enum('visibility', ['public', 'followers'])->default('public');
    $t->timestamps();

    $t->index(['user_id','created_at']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
