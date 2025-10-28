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
            // $table->string('won_by')->nullable()->after('status');
            // $table->text('resolution_notes')->nullable()->after('won_by');
            // $table->timestamp('resolved_at')->nullable()->after('resolution_notes');
            // $table->timestamp('closed_at')->nullable()->after('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['won_by', 'resolution_notes', 'resolved_at', 'closed_at']);
        });
    }
};
