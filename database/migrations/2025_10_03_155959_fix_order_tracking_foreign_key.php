<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('order_trackings', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
            $table->unsignedBigInteger('store_order_id')->after('id');
            $table->foreign('store_order_id')
                  ->references('id')
                  ->on('store_orders')
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::table('order_trackings', function (Blueprint $table) {
            $table->dropForeign(['store_order_id']);
            $table->dropColumn('store_order_id');

            $table->unsignedBigInteger('order_id')->after('id');
            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');
        });
    }
};
