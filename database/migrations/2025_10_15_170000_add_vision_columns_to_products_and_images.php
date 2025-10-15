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
            $table->string('vision_product_name')->nullable();
            $table->string('vision_product_set')->nullable();
            $table->enum('vision_index_status', ['pending', 'indexed', 'failed'])->default('pending');
            $table->timestamp('vision_indexed_at')->nullable();
            $table->text('vision_last_error')->nullable();
        });

        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->string('gcs_uri')->nullable();
                $table->string('vision_reference_image_name')->nullable();
                $table->enum('vision_index_status', ['pending', 'indexed', 'failed'])->default('pending');
                $table->text('vision_last_error')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'vision_product_name',
                'vision_product_set',
                'vision_index_status',
                'vision_indexed_at',
                'vision_last_error'
            ]);
        });

        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropColumn([
                    'gcs_uri',
                    'vision_reference_image_name',
                    'vision_index_status',
                    'vision_last_error'
                ]);
            });
        }
    }
};
