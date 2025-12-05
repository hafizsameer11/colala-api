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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('visibility')->default(1)->index();
        });
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('visibility')->default(1)->index();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('visibility')->default(1)->index();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('visibility')->default(1)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
