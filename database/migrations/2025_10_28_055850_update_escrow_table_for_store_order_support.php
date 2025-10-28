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
        Schema::table('escrows', function (Blueprint $table) {
            // Make order_item_id nullable (for store-order level escrow)
            $table->unsignedBigInteger('order_item_id')->nullable()->change();
            
            // Add store_order_id for new flow
            $table->unsignedBigInteger('store_order_id')->nullable()->after('order_id');
            $table->foreign('store_order_id')->references('id')->on('store_orders')->onDelete('cascade');
            
            // Make shipping_fee nullable (already might be, but ensure it)
            $table->decimal('shipping_fee', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escrows', function (Blueprint $table) {
            $table->dropForeign(['store_order_id']);
            $table->dropColumn('store_order_id');
            
            // Note: Can't easily revert nullable changes in down migration
            // Old records would still work
        });
    }
};
