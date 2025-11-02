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
            // Add referral fee (amount seller wants to give per referral)
            $table->decimal('referral_fee', 10, 2)->nullable()->after('quantity');
            // Add limit for how many people can receive referral fee for this product
            $table->integer('referral_person_limit')->nullable()->after('referral_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['referral_fee', 'referral_person_limit']);
        });
    }
};
