<?php

namespace App\Console\Commands;

use App\Jobs\IndexOldProductImagesJob;
use App\Models\ProductImage;
use App\Services\ReplicateEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class ReplicateIndexProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'replicate:index-products 
                            {--chunk=50 : Number of images to process per batch}
                            {--queue=default : Queue name to dispatch jobs to}
                            {--test : Test API connection without dispatching jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all product images with CLIP embeddings using Replicate API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting product image indexing with CLIP embeddings...');

        // Test API connection first
        if (!$this->testApiConnection()) {
            $this->error('❌ API connection test failed. Please check your REPLICATE_API_TOKEN.');
            return self::FAILURE;
        }

        if ($this->option('test')) {
            $this->info('✅ API connection test passed. Exiting test mode.');
            return self::SUCCESS;
        }

        $chunkSize = (int) $this->option('chunk');
        $queueName = $this->option('queue');

        // Get total count
        $totalImages = ProductImage::count();
        $this->info("📊 Found {$totalImages} product images to process");

        if ($totalImages === 0) {
            $this->warn('⚠️  No product images found to process.');
            return self::SUCCESS;
        }

        // Process in chunks
        $processed = 0;
        $bar = $this->output->createProgressBar($totalImages);
        $bar->start();

        ProductImage::chunk($chunkSize, function ($images) use (&$processed, $queueName, $bar) {
            foreach ($images as $image) {
                // Dispatch job to queue
                IndexOldProductImagesJob::dispatch($image->id)->onQueue($queueName);
                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("✅ All {$processed} product images queued for embedding.");
        $this->info("📝 Check storage/logs/replicate_embeddings.log for progress updates.");
        $this->info("⚡ Make sure your queue worker is running: php artisan queue:work --queue={$queueName}");

        return self::SUCCESS;
    }

    /**
     * Test the API connection
     */
    private function testApiConnection(): bool
    {
        $this->info('🔍 Testing API connection...');

        try {
            $service = new ReplicateEmbeddingService();
            $isConnected = $service->testConnection();

            if ($isConnected) {
                $this->info('✅ API connection successful');
                return true;
            } else {
                $this->error('❌ API connection failed');
                return false;
            }
        } catch (\Exception $e) {
            $this->error("❌ API connection error: {$e->getMessage()}");
            return false;
        }
    }
}
