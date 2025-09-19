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
         Schema::create('store_business_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('registered_name')->nullable();
            $table->enum('business_type', ['BN','LTD'])->nullable();
            $table->string('nin_number')->nullable();
            $table->string('bn_number')->nullable();
            $table->string('cac_number')->nullable();
            $table->string('nin_document')->nullable();
            $table->string('cac_document')->nullable();
            $table->string('utility_bill')->nullable();
            $table->string('store_video')->nullable();
            $table->boolean('has_physical_store')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_business_details');
    }
};
