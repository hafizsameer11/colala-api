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
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            // Add Flutterwave fields
            $table->string('bank_code')->nullable()->after('bank_name');
            $table->string('reference')->unique()->nullable()->after('account_name');
            $table->string('flutterwave_transfer_id')->nullable()->after('reference');
            $table->text('remarks')->nullable()->after('flutterwave_transfer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn(['bank_code', 'reference', 'flutterwave_transfer_id', 'remarks']);
        });
    }
};
