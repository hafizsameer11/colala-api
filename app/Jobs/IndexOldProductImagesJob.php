<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductEmbedding;
use App\Services\ReplicateEmbeddingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class IndexOldProductImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60; // 1 minute base backoff

    private int $productImageId;
    private ReplicateEmbeddingService $embeddingService;

    /**
     * Create a new job instance.
     */
    public function __construct(int $productImageId)
    {
        $this->productImageId = $productImageId;
        $this->embeddingService = new ReplicateEmbeddingService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $productImage = ProductImage::with('product')->find($this->productImageId);
            
            if (!$productImage) {
                $this->logError("ProductImage #{$this->productImageId} not found");
                return;
            }

            // Check if embedding already exists
            $existingEmbedding = ProductEmbedding::where('product_id', $productImage->product_id)
                ->where('image_id', $productImage->id)
                ->first();

            if ($existingEmbedding) {
                $this->logInfo("ProductImage #{$productImage->id} for Product #{$productImage->product_id} already has embedding, skipping");
                return;
            }

            // Generate full image URL
            $imageUrl = $this->getImageUrl($productImage->path);
            
            if (!$imageUrl) {
                $this->logError("ProductImage #{$productImage->id} - Invalid image path: {$productImage->path}");
                return;
            }

            // Generate embedding
            $embedding = $this->embeddingService->generateEmbedding($imageUrl);

            // Store embedding
            ProductEmbedding::create([
                'product_id' => $productImage->product_id,
                'image_id' => $productImage->id,
                'embedding' => $embedding,
            ]);

            $this->logInfo("Indexed ProductImage #{$productImage->id} for Product #{$productImage->product_id}");

        } catch (Exception $e) {
            $this->logError("Failed ProductImage #{$this->productImageId} - error: {$e->getMessage()}");
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        $this->logError("Job failed permanently for ProductImage #{$this->productImageId} - error: {$exception->getMessage()}");
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 300]; // 1 min, 2 min, 5 min
    }

    /**
     * Get the full URL for the image
     */
    private function getImageUrl(string $path): ?string
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                return asset('storage/' . $path);
            }
            
            // If it's already a full URL, return as is
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Log info message to replicate_embeddings.log
     */
    private function logInfo(string $message): void
    {
        $logMessage = '[' . now()->format('Y-m-d H:i:s') . '] ' . $message;
        $logFile = storage_path('logs/replicate_embeddings.log');
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log error message to replicate_embeddings.log
     */
    private function logError(string $message): void
    {
        $logMessage = '[' . now()->format('Y-m-d H:i:s') . '] ' . $message;
        $logFile = storage_path('logs/replicate_embeddings.log');
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
