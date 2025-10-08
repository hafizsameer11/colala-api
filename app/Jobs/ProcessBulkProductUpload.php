<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\BulkProductUploadService;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBulkProductUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    private $userId;
    private $csvData;
    private $uploadId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, array $csvData, string $uploadId)
    {
        $this->userId = $userId;
        $this->csvData = $csvData;
        $this->uploadId = $uploadId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting bulk upload processing for user {$this->userId}, upload ID: {$this->uploadId}");

            // Update upload status to processing
            $this->updateUploadStatus('processing');

            $results = [
                'success' => [],
                'errors' => [],
                'total_processed' => 0,
                'success_count' => 0,
                'error_count' => 0
            ];

            $store = Store::where('user_id', $this->userId)->first();
            
            if (!$store) {
                throw new Exception('Store not found');
            }

            $googleDriveService = new GoogleDriveService();

            foreach ($this->csvData as $index => $row) {
                $results['total_processed']++;
                
                try {
                    // Validate row data
                    $this->validateRow($row, $index + 1);
                    
                    // Process the product
                    $product = $this->createProductFromRow($row, $store->id, $googleDriveService);
                    
                    $results['success'][] = [
                        'row' => $index + 1,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'message' => 'Product created successfully'
                    ];
                    
                    $results['success_count']++;
                    
                } catch (Exception $e) {
                    Log::error("Error processing row " . ($index + 1) . ": " . $e->getMessage());
                    
                    $results['errors'][] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage(),
                        'data' => $row
                    ];
                    
                    $results['error_count']++;
                }
            }

            // Update upload status to completed
            $this->updateUploadStatus('completed', $results);

            Log::info("Bulk upload processing completed for user {$this->userId}, upload ID: {$this->uploadId}");

        } catch (Exception $e) {
            Log::error("Bulk upload processing failed for user {$this->userId}, upload ID: {$this->uploadId}: " . $e->getMessage());
            
            // Update upload status to failed
            $this->updateUploadStatus('failed', null, $e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Bulk upload job failed for user {$this->userId}, upload ID: {$this->uploadId}: " . $exception->getMessage());
        
        $this->updateUploadStatus('failed', null, $exception->getMessage());
    }

    /**
     * Validate CSV row data
     */
    private function validateRow(array $row, int $rowNumber): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make($row, [
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'has_variants' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
            'loyality_points_applicable' => 'nullable|boolean',
            'video_url' => 'nullable|string',
            'main_image_url' => 'nullable|string',
            'image_urls' => 'nullable|string',
            'variants_data' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new Exception("Row {$rowNumber}: " . implode(', ', $validator->errors()->all()));
        }
    }

    /**
     * Create product from CSV row
     */
    private function createProductFromRow(array $row, int $storeId, GoogleDriveService $googleDriveService): Product
    {
        return DB::transaction(function () use ($row, $storeId, $googleDriveService) {
            // Prepare product data
            $productData = [
                'store_id' => $storeId,
                'name' => $row['name'],
                'category_id' => $row['category_id'],
                'brand' => $row['brand'] ?? null,
                'description' => $row['description'],
                'price' => $row['price'],
                'discount_price' => $row['discount_price'] ?? null,
                'has_variants' => filter_var($row['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'status' => $row['status'] ?? 'active',
                'loyality_points_applicable' => filter_var($row['loyality_points_applicable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'quantity' => 0,
            ];

            // Create the product
            $product = Product::create($productData);

            // Handle video download
            if (!empty($row['video_url'])) {
                $this->downloadAndStoreVideo($product, $row['video_url'], $googleDriveService);
            }

            // Handle main image download
            if (!empty($row['main_image_url'])) {
                $this->downloadAndStoreMainImage($product, $row['main_image_url'], $googleDriveService);
            }

            // Handle additional images
            if (!empty($row['image_urls'])) {
                $this->downloadAndStoreImages($product, $row['image_urls'], $googleDriveService);
            }

            // Handle variants
            if (!empty($row['variants_data'])) {
                $this->createVariantsFromData($product, $row['variants_data'], $googleDriveService);
            }

            // Update product quantity based on variants
            $this->updateProductQuantity($product);

            return $product->load(['images', 'variants.images']);
        });
    }

    /**
     * Download and store video
     */
    private function downloadAndStoreVideo(Product $product, string $videoUrl, GoogleDriveService $googleDriveService): void
    {
        try {
            if ($googleDriveService->isValidGoogleDriveUrl($videoUrl)) {
                $videoPath = $googleDriveService->downloadFile($videoUrl, 'products/videos');
                $product->update(['video' => $videoPath]);
            }
        } catch (Exception $e) {
            throw new Exception("Video download failed: " . $e->getMessage());
        }
    }

    /**
     * Download and store main image
     */
    private function downloadAndStoreMainImage(Product $product, string $imageUrl, GoogleDriveService $googleDriveService): void
    {
        try {
            if ($googleDriveService->isValidGoogleDriveUrl($imageUrl)) {
                $imagePath = $googleDriveService->downloadFile($imageUrl, 'products');
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $imagePath,
                    'is_main' => true,
                ]);
            }
        } catch (Exception $e) {
            throw new Exception("Main image download failed: " . $e->getMessage());
        }
    }

    /**
     * Download and store additional images
     */
    private function downloadAndStoreImages(Product $product, string $imageUrls, GoogleDriveService $googleDriveService): void
    {
        try {
            $urls = array_filter(array_map('trim', explode(',', $imageUrls)));
            
            foreach ($urls as $url) {
                if ($googleDriveService->isValidGoogleDriveUrl($url)) {
                    $imagePath = $googleDriveService->downloadFile($url, 'products');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $imagePath,
                        'is_main' => false,
                    ]);
                }
            }
        } catch (Exception $e) {
            throw new Exception("Images download failed: " . $e->getMessage());
        }
    }

    /**
     * Create variants from JSON data
     */
    private function createVariantsFromData(Product $product, string $variantsData, GoogleDriveService $googleDriveService): void
    {
        try {
            $variants = json_decode($variantsData, true);
            
            if (!is_array($variants)) {
                throw new Exception("Invalid variants data format");
            }

            foreach ($variants as $variantData) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variantData['sku'] ?? null,
                    'color' => $variantData['color'] ?? null,
                    'size' => $variantData['size'] ?? null,
                    'price' => $variantData['price'] ?? $product->price,
                    'discount_price' => $variantData['discount_price'] ?? null,
                    'stock' => $variantData['stock'] ?? 0,
                ]);

                // Handle variant images
                if (!empty($variantData['image_urls'])) {
                    $this->downloadVariantImages($variant, $variantData['image_urls'], $googleDriveService);
                }
            }
        } catch (Exception $e) {
            throw new Exception("Variants creation failed: " . $e->getMessage());
        }
    }

    /**
     * Download variant images
     */
    private function downloadVariantImages(ProductVariant $variant, string $imageUrls, GoogleDriveService $googleDriveService): void
    {
        try {
            $urls = array_filter(array_map('trim', explode(',', $imageUrls)));
            
            foreach ($urls as $url) {
                if ($googleDriveService->isValidGoogleDriveUrl($url)) {
                    $imagePath = $googleDriveService->downloadFile($url, 'products');
                    ProductImage::create([
                        'product_id' => $variant->product_id,
                        'variant_id' => $variant->id,
                        'path' => $imagePath,
                    ]);
                }
            }
        } catch (Exception $e) {
            throw new Exception("Variant images download failed: " . $e->getMessage());
        }
    }

    /**
     * Update product quantity based on variants
     */
    private function updateProductQuantity(Product $product): void
    {
        $totalStock = $product->variants()->sum('stock');
        $product->update(['quantity' => $totalStock]);
    }

    /**
     * Update upload status in database
     */
    private function updateUploadStatus(string $status, ?array $results = null, ?string $errorMessage = null): void
    {
        try {
            DB::table('bulk_upload_jobs')->where('upload_id', $this->uploadId)->update([
                'status' => $status,
                'results' => $results ? json_encode($results) : null,
                'error_message' => $errorMessage,
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error("Failed to update upload status: " . $e->getMessage());
        }
    }
}
