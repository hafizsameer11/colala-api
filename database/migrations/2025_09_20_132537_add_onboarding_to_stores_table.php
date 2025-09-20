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
            $table->enum('onboarding_status', ['draft','pending_review','approved'])
                  ->default('draft')->after('status');
            $table->unsignedTinyInteger('onboarding_level')->default(1)->after('onboarding_status');
            $table->unsignedTinyInteger('onboarding_percent')->default(0)->after('onboarding_level');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['onboarding_status','onboarding_level','onboarding_percent']);
        });
    }
};
