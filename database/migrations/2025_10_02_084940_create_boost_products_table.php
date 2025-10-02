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

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // DATE columns can't safely default to CURRENT_DATE on many MySQL/MariaDB versions.
            // Make it nullable and set today() in app code when creating.
            $table->date('start_date')->nullable();

            $table->enum('status', ['draft','scheduled','running','paused','completed','cancelled','pending'])
                  ->default('pending');

            $table->unsignedSmallInteger('duration')->default(1); // days

            // Use numeric types (change to decimal if you prefer minor units vs. currency)
            $table->unsignedInteger('budget')->nullable();        // daily budget (minor units)
            $table->string('location')->nullable();
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('total_amount')->default(0);  // budget*duration + fees
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->unsignedInteger('clicks')->default(0);

            $table->enum('payment_method', ['wallet','card','bank'])->nullable();
            $table->enum('payment_status', ['pending','paid','failed','refunded'])->default('pending');

            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['start_date']);
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
