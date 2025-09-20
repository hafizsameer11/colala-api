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
         Schema::create('store_onboarding_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('level');     // 1,2,3
            $t->string('key')->nullable();                    // e.g., level1.basic
            $t->enum('status', ['pending','done'])->default('pending');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['store_id','key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_onboarding_steps');
    }
};
