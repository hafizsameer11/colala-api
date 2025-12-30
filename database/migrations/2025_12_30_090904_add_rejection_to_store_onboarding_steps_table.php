<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_onboarding_steps', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
        });

        // Modify enum to include 'rejected' status
        DB::statement("ALTER TABLE store_onboarding_steps MODIFY COLUMN status ENUM('pending', 'done', 'rejected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_onboarding_steps', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        // Revert enum back to original values
        DB::statement("ALTER TABLE store_onboarding_steps MODIFY COLUMN status ENUM('pending', 'done') DEFAULT 'pending'");
    }
};
