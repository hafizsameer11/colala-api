<?php 


// database/migrations/2025_10_03_000007_create_saved_cards_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('saved_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // actual card number and cvv should never be stored
            $table->string('card_holder'); 
            $table->string('last4', 4);              // ****1234
            $table->string('brand')->nullable();    // Visa, MasterCard
            $table->string('expiry_month', 2);
            $table->string('expiry_year', 4);

            $table->string('gateway_ref')->nullable(); // token/ref from Flutterwave/Paystack/Stripe
            $table->boolean('is_active')->default(false);
            $table->boolean('is_autodebit')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('saved_cards');
    }
};
