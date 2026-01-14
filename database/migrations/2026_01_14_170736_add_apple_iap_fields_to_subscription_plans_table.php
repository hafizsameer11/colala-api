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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('apple_product_id_monthly', 255)->nullable()->after('duration_days');
            $table->string('apple_product_id_annual', 255)->nullable()->after('apple_product_id_monthly');
        });

        // Add indexes for faster lookups
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->index('apple_product_id_monthly', 'idx_plans_apple_product_id_monthly');
            $table->index('apple_product_id_annual', 'idx_plans_apple_product_id_annual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropIndex('idx_plans_apple_product_id_monthly');
            $table->dropIndex('idx_plans_apple_product_id_annual');
            $table->dropColumn(['apple_product_id_monthly', 'apple_product_id_annual']);
        });
    }
};
