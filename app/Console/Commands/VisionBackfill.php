<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\ProductImage;
use App\Jobs\Vision\IndexProductToVision;
use App\Jobs\Vision\IndexProductImageToVision;

class VisionBackfill extends Command
{
    protected $signature = 'vision:backfill {--chunk=200}';
    protected $description = 'Index existing products & images to Google Vision';

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk') ?: 200;

        $this->info('Dispatching Product indexing...');
        Product::query()->select('id')->orderBy('id')
            ->chunk($chunk, function ($products) {
                foreach ($products as $product) {
                    dispatch(new IndexProductToVision($product->id))->onQueue('vision');
                }
            });

        $this->info('Dispatching ProductImage indexing...');
        if (class_exists(ProductImage::class)) {
            ProductImage::query()->select('id')->orderBy('id')
                ->chunk($chunk, function ($images) {
                    foreach ($images as $image) {
                        dispatch(new IndexProductImageToVision($image->id))->onQueue('vision');
                    }
                });
        }

        $this->info('✅ Backfill jobs queued. Run the worker: php artisan queue:work --queue=vision,default');
        return self::SUCCESS;
    }
}
