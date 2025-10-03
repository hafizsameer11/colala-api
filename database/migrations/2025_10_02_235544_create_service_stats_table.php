<?php 

// database/migrations/2025_10_03_000006_create_service_stats_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('service_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', [
                'view',        // when service detail opened
                'impression',  // when service shown in listing
                'click',       // e.g. profile or external link
                'chat',        // start chat about service
                'phone_view'   // phone number revealed
            ]);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('ip')->nullable();
            $table->timestamps();

            $table->index(['service_id','event_type']);
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('service_stats');
    }
};
