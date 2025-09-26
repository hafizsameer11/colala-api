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
        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('store_order_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('chats', 'type')) {
                $table->string('type')->default('general')->after('service_id');
            }

            // make store_order_id nullable if not already
            if (Schema::hasColumn('chats', 'store_order_id')) {
                $table->unsignedBigInteger('store_order_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('chats', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
        });
    }
};
