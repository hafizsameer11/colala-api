<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Category;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BulkProductUploadService
{
    private $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Process bulk product upload from CSV
     */
    public function processBulkUpload(array $csvData): array
    {
        $results = [
            'success' => [],
            'errors' => [],
            'total_processed' => 0,
            'success_count' => 0,
            'error_count' => 0
        ];

        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        
        if (!$store) {
            throw new Exception('Store not found');
        }

        foreach ($csvData as $index => $row) {
            $results['total_processed']++;
            
            try {
                // Validate row data
                $this->validateRow($row, $index + 1);
                
                // Process the product
                $product = $this->createProductFromRow($row, $store->id);
                
                $results['success'][] = [
                    'row' => $index + 1,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'message' => 'Product created successfully'
                ];
                
                $results['success_count']++;
                
            } catch (Exception $e) {
                $results['errors'][] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
                
                $results['error_count']++;
            }
        }

        return $results;
    }

    /**
     * Validate CSV row data
     */
    private function validateRow(array $row, int $rowNumber): void
    {
        $validator = Validator::make($row, [
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
    private function createProductFromRow(array $row, int $storeId): Product
    {
        return DB::transaction(function () use ($row, $storeId) {
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
                $this->downloadAndStoreVideo($product, $row['video_url']);
            }

            // Handle main image download
            if (!empty($row['main_image_url'])) {
                $this->downloadAndStoreMainImage($product, $row['main_image_url']);
            }

            // Handle additional images
            if (!empty($row['image_urls'])) {
                $this->downloadAndStoreImages($product, $row['image_urls']);
            }

            // Handle variants
            if (!empty($row['variants_data'])) {
                $this->createVariantsFromData($product, $row['variants_data']);
            }

            // Update product quantity based on variants
            $this->updateProductQuantity($product);

            return $product->load(['images', 'variants.images']);
        });
    }

    /**
     * Download and store video
     */
    private function downloadAndStoreVideo(Product $product, string $videoUrl): void
    {
        try {
            if ($this->googleDriveService->isValidGoogleDriveUrl($videoUrl)) {
                $videoPath = $this->googleDriveService->downloadFile($videoUrl, 'products/videos');
                $product->update(['video' => $videoPath]);
            }
        } catch (Exception $e) {
            throw new Exception("Video download failed: " . $e->getMessage());
        }
    }

    /**
     * Download and store main image
     */
    private function downloadAndStoreMainImage(Product $product, string $imageUrl): void
    {
        try {
            if ($this->googleDriveService->isValidGoogleDriveUrl($imageUrl)) {
                $imagePath = $this->googleDriveService->downloadFile($imageUrl, 'products');
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
    private function downloadAndStoreImages(Product $product, string $imageUrls): void
    {
        try {
            $urls = array_filter(array_map('trim', explode(',', $imageUrls)));
            
            foreach ($urls as $url) {
                if ($this->googleDriveService->isValidGoogleDriveUrl($url)) {
                    $imagePath = $this->googleDriveService->downloadFile($url, 'products');
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
    private function createVariantsFromData(Product $product, string $variantsData): void
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
                    $this->downloadVariantImages($variant, $variantData['image_urls']);
                }
            }
        } catch (Exception $e) {
            throw new Exception("Variants creation failed: " . $e->getMessage());
        }
    }

    /**
     * Download variant images
     */
    private function downloadVariantImages(ProductVariant $variant, string $imageUrls): void
    {
        try {
            $urls = array_filter(array_map('trim', explode(',', $imageUrls)));
            
            foreach ($urls as $url) {
                if ($this->googleDriveService->isValidGoogleDriveUrl($url)) {
                    $imagePath = $this->googleDriveService->downloadFile($url, 'products');
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
     * Get CSV template structure
     */
    public function getCsvTemplate(): array
    {
        return [
            'headers' => [
                'name',
                'category_id', 
                'brand',
                'description',
                'price',
                'discount_price',
                'has_variants',
                'status',
                'loyality_points_applicable',
                'video_url',
                'main_image_url',
                'image_urls',
                'variants_data'
            ],
            'sample_row' => [
                'name' => 'Sample Product',
                'category_id' => '1',
                'brand' => 'Sample Brand',
                'description' => 'This is a sample product description',
                'price' => '99.99',
                'discount_price' => '79.99',
                'has_variants' => 'true',
                'status' => 'active',
                'loyality_points_applicable' => 'true',
                'video_url' => 'https://drive.google.com/file/d/1ABC123XYZ/view',
                'main_image_url' => 'https://drive.google.com/file/d/1DEF456UVW/view',
                'image_urls' => 'https://drive.google.com/file/d/1GHI789RST/view,https://drive.google.com/file/d/1JKL012MNO/view',
                'variants_data' => json_encode([
                    [
                        'sku' => 'VAR001',
                        'color' => 'Red',
                        'size' => 'M',
                        'price' => '99.99',
                        'discount_price' => '79.99',
                        'stock' => 10,
                        'image_urls' => 'https://drive.google.com/file/d/1PQR345STU/view'
                    ],
                    [
                        'sku' => 'VAR002',
                        'color' => 'Blue',
                        'size' => 'L',
                        'price' => '99.99',
                        'discount_price' => '79.99',
                        'stock' => 15,
                        'image_urls' => 'https://drive.google.com/file/d/1VWX678YZA/view'
                    ]
                ])
            ],
            'instructions' => [
                'name' => 'Product name (required)',
                'category_id' => 'Category ID from categories table (required)',
                'brand' => 'Product brand (optional)',
                'description' => 'Product description (required)',
                'price' => 'Base price (required)',
                'discount_price' => 'Discounted price (optional)',
                'has_variants' => 'true/false (optional, default: false)',
                'status' => 'active/inactive (optional, default: active)',
                'loyality_points_applicable' => 'true/false (optional, default: false)',
                'video_url' => 'Google Drive video URL (optional)',
                'main_image_url' => 'Google Drive main image URL (optional)',
                'image_urls' => 'Comma-separated Google Drive image URLs (optional)',
                'variants_data' => 'JSON string with variant data (optional)'
            ]
        ];
    }
}
