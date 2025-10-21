<?php 

// database/migrations/2025_10_21_000001_add_shipping_fee_to_escrows_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('escrows', function (Blueprint $table) {
            // Use decimal to match typical money schema; adjust precision if needed
            $table->decimal('shipping_fee', 14, 2)->nullable()->after('amount');
            // Optional optimization:
            // $table->index('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('escrows', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
    }
};
