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
        Schema::table('disputes', function (Blueprint $table) {
            // Add dispute_chat_id column
            $table->foreignId('dispute_chat_id')->nullable()->after('chat_id')->constrained()->cascadeOnDelete();
            // Keep chat_id for backward compatibility but make it nullable
            $table->foreignId('chat_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropForeign(['dispute_chat_id']);
            $table->dropColumn('dispute_chat_id');
            // Revert chat_id to required if needed
            $table->foreignId('chat_id')->nullable(false)->change();
        });
    }
};
