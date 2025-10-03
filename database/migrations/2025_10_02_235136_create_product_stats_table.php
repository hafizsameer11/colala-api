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
        Schema::create('product_stats', function (Blueprint $table) {
             $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('event_type')->default('impression');
            // $table->enum('event_type', [
            //     'view',        // when product detail page opened
            //     'impression',  // when product is shown in listing
            //     'click',       // clicked (like profile click, CTA)
            //     'add_to_cart',
            //     'order',
            //     'chat'
            // ]);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('ip')->nullable();
            $table->timestamps();

            $table->index(['product_id','event_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stats');
    }
};
