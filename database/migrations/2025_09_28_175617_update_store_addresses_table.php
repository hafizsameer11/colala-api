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
        Schema::table('store_addresses', function (Blueprint $table) {
             $table->dropColumn(['state', 'local_government', 'variant','price', 'is_free']);

            // Add the correct columns
            $table->string('full_address')->after('local_government');
            $table->boolean('is_main')->default(false)->after('full_address');
            $table->json('opening_hours')->nullable()->after('is_main');
        });
    }

    /**
     * Reverse the migrations.
     */
       public function down(): void
    {
        Schema::table('store_addresses', function (Blueprint $table) {
            // Rollback: re-add dropped columns
            $table->string('variant')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_free')->default(false);

            // Drop the new columns
            $table->dropColumn(['full_address', 'is_main', 'opening_hours']);
        });
    }
};
