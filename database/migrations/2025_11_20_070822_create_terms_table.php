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
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->text('buyer_privacy_policy')->nullable();
            $table->text('buyer_terms_and_condition')->nullable();
            $table->text('buyer_return_policy')->nullable();
            $table->text('seller_onboarding_policy')->nullable();
            $table->text('seller_privacy_policy')->nullable();
            $table->text('seller_terms_and_condition')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
