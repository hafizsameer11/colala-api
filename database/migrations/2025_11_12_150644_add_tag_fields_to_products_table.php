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
            $table->text('tag1')->nullable()->after('referral_person_limit');
            $table->text('tag2')->nullable()->after('tag1');
            $table->text('tag3')->nullable()->after('tag2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tag1', 'tag2', 'tag3']);
        });
    }
};
