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
        Schema::table('saved_items', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->after('product_id')->nullable();
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->unsignedBigInteger('post_id')->after('service_id')->nullable();
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            //make product_id nullable
            $table->unsignedBigInteger('product_id')->nullable()->change();
            //add unique constraint for user_id, product_id, service_id, post_id combination
            $table->unique(['user_id','product_id','service_id','post_id']);
            // --- IGNORE ---
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_items', function (Blueprint $table) {
            //
        });
    }
};
