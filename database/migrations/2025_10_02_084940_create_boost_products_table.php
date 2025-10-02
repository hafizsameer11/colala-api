<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boost_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id');
            $table->date('start_date')->default(DB::raw('CURRENT_DATE'));
            $table->string('status')->default('pending');
            $table->integer('duration')->default(1);
            $table->string('budget')->nullable();
            $table->string('location')->nullable();
            $table->string('reach')->nullable();
            $table->string('total_amount')->nullable();
            $table->string('impressions')->nullable();
            $table->string('cpc')->nullable();
            $table->string('clicks')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boost_products');
    }
};
