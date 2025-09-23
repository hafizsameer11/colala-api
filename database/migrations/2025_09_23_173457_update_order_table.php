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
         Schema::table('orders', function (Blueprint $table) {
                        $table->foreignId('delivery_address_id')->nullable()->constrained('user_addresses')->nullOnDelete();

        });
        Schema::table('store_orders', function (Blueprint $table) {
                                   $table->foreignId('delivery_pricing_id')->nullable()->constrained('store_delivery_pricings')->nullOnDelete();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_address_id']);
            $table->dropColumn('delivery_address_id');
        });
        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_pricing_id']);
            $table->dropColumn('delivery_pricing_id');
        });
    }
};
