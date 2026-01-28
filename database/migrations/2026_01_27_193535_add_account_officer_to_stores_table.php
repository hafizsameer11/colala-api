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
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('account_officer_id')->nullable()->after('user_id');
            $table->foreign('account_officer_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            $table->index('account_officer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['account_officer_id']);
            $table->dropIndex(['account_officer_id']);
            $table->dropColumn('account_officer_id');
        });
    }
};
