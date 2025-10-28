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
        // Update store_orders table
        Schema::table('store_orders', function (Blueprint $table) {
            // Add new fields for acceptance flow
            $table->text('rejection_reason')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->date('estimated_delivery_date')->nullable()->after('rejected_at');
            $table->string('delivery_method')->nullable()->after('estimated_delivery_date');
            $table->text('delivery_notes')->nullable()->after('delivery_method');
            // $table->float('shipping_fee')->nullable()->after('delivery_notes');
        });

        // Update orders table
        Schema::table('orders', function (Blueprint $table) {
            // Add overall order status
            $table->string('status')->default('pending')->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropColumn([
                'rejection_reason',
                'accepted_at',
                'rejected_at',
                'estimated_delivery_date',
                'delivery_method',
                'delivery_notes'
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['status', 'paid_at']);
        });
    }
};

