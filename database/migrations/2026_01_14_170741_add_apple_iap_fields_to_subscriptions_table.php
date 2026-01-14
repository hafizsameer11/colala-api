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
        // Modify payment_method enum to include 'apple_iap'
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE subscriptions MODIFY COLUMN payment_method ENUM('wallet','flutterwave','paystack','apple_iap') NULL");
        
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('apple_transaction_id', 255)->nullable()->after('transaction_ref');
            $table->string('apple_original_transaction_id', 255)->nullable()->after('apple_transaction_id');
            $table->text('apple_receipt_data')->nullable()->after('apple_original_transaction_id');
            $table->boolean('is_auto_renewable')->default(false)->after('apple_receipt_data');
        });

        // Add indexes for faster lookups
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('apple_transaction_id', 'idx_subscriptions_apple_transaction_id');
            $table->index('apple_original_transaction_id', 'idx_subscriptions_apple_original_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_apple_transaction_id');
            $table->dropIndex('idx_subscriptions_apple_original_transaction_id');
            $table->dropColumn([
                'apple_transaction_id',
                'apple_original_transaction_id',
                'apple_receipt_data',
                'is_auto_renewable'
            ]);
        });
        
        // Revert payment_method enum
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE subscriptions MODIFY COLUMN payment_method ENUM('wallet','flutterwave','paystack') NULL");
    }
};
