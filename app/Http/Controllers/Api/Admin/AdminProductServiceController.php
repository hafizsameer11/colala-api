<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\SubService;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminProductServiceController extends Controller
{
    /**
     * Get all stores (name, picture, id only)
     */
    public function getStores()
    {
        try {
            $stores = Store::select('id', 'store_name', 'profile_image', 'banner_image')
                ->with('user:id,full_name,email')
                ->get()
                ->map(function ($store) {
                    return [
                        'id' => $store->id,
                        'store_name' => $store->store_name,
                        'profile_image' => $store->profile_image ? asset('storage/' . $store->profile_image) : null,
                        'banner_image' => $store->banner_image ? asset('storage/' . $store->banner_image) : null,
                        'owner_name' => $store->user->full_name ?? null,
                        'owner_email' => $store->user->email ?? null,
                    ];
                });

            return ResponseHelper::success([
                'stores' => $stores,
                'total_stores' => $stores->count()
            ], 'Stores retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create product for any store (admin)
     */
    public function createProduct(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'discount_price' => 'nullable|numeric|min:0',
                'category_id' => 'required|exists:categories,id',
                'quantity' => 'required|integer|min:0',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'video' => 'nullable|file|mimes:mp4,avi,mov|max:10240',
                'variants' => 'nullable|array',
                'variants.*.sku' => 'nullable|string|max:100',
                'variants.*.color' => 'nullable|string|max:50',
                'variants.*.size' => 'nullable|string|max:50',
                'variants.*.price' => 'nullable|numeric|min:0',
                'variants.*.discount_price' => 'nullable|numeric|min:0',
                'variants.*.stock' => 'nullable|integer|min:0',
                'variants.*.images' => 'nullable|array',
                'variants.*.images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $data = $request->all();
            $data['store_id'] = $request->store_id; // Admin can specify store_id

            $product = DB::transaction(function () use ($data, $request) {
                // Create main product
                $product = Product::create([
                    'store_id' => $data['store_id'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'discount_price' => $data['discount_price'] ?? null,
                    'category_id' => $data['category_id'],
                    'quantity' => $data['quantity'],
                ]);

                // Handle video upload
                if ($request->hasFile('video')) {
                    $videoPath = $request->file('video')->store('products/videos', 'public');
                    $product->update(['video' => $videoPath]);
                }

                // Handle product images
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $index => $file) {
                        $path = $file->store('products', 'public');
                        ProductImage::create([
                            'product_id' => $product->id,
                            'path' => $path,
                            'is_main' => $index === 0,
                        ]);
                    }
                }

                // Handle variants
                $totalQuantity = $data['quantity'];
                if (!empty($data['variants'])) {
                    foreach ($data['variants'] as $variantData) {
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $variantData['sku'] ?? null,
                            'color' => $variantData['color'] ?? null,
                            'size' => $variantData['size'] ?? null,
                            'price' => $variantData['price'] ?? null,
                            'discount_price' => $variantData['discount_price'] ?? null,
                            'stock' => $variantData['stock'] ?? 0,
                        ]);

                        $totalQuantity += $variantData['stock'] ?? 0;

                        // Handle variant images
                        if (!empty($variantData['images'])) {
                            foreach ($variantData['images'] as $file) {
                                $path = $file->store('products', 'public');
                                ProductImage::create([
                                    'product_id' => $product->id,
                                    'variant_id' => $variant->id,
                                    'path' => $path,
                                ]);
                            }
                        }
                    }
                }

                // Update total quantity
                $product->update(['quantity' => $totalQuantity]);

                return $product->load(['images', 'variants.images', 'store', 'category']);
            });

            return ResponseHelper::success($product, 'Product created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create service for any store (admin)
     */
    public function createService(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => 'required|exists:stores,id',
                'name' => 'required|string|max:255',
                'short_description' => 'nullable|string|max:500',
                'full_description' => 'required|string',
                'price_from' => 'nullable|numeric|min:0',
                'price_to' => 'nullable|numeric|min:0',
                'discount_price' => 'nullable|numeric|min:0',
                'category_id' => 'required|exists:categories,id',
                'service_category_id' => 'nullable|exists:service_categories,id',
                'status' => 'nullable|in:active,inactive,draft',
                'video' => 'nullable|file|mimes:mp4,avi,mov|max:10240',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpeg,png,jpg,gif,mp4,avi,mov|max:10240',
                'sub_services' => 'nullable|array',
                'sub_services.*.name' => 'required|string|max:255',
                'sub_services.*.price_from' => 'nullable|numeric|min:0',
                'sub_services.*.price_to' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $data = $request->all();
            $data['store_id'] = $request->store_id; // Admin can specify store_id

            $service = DB::transaction(function () use ($data, $request) {
                // Handle video upload
                if ($request->hasFile('video')) {
                    $videoPath = $request->file('video')->store('services/videos', 'public');
                    $data['video'] = $videoPath;
                }

                // Create service
                $service = Service::create([
                    'store_id' => $data['store_id'],
                    'category_id' => $data['category_id'],
                    'service_category_id' => $data['service_category_id'] ?? null,
                    'name' => $data['name'],
                    'short_description' => $data['short_description'] ?? null,
                    'full_description' => $data['full_description'],
                    'price_from' => $data['price_from'] ?? null,
                    'price_to' => $data['price_to'] ?? null,
                    'discount_price' => $data['discount_price'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'video' => $data['video'] ?? null,
                ]);

                // Handle media uploads
                if ($request->hasFile('media')) {
                    foreach ($request->file('media') as $file) {
                        $path = $file->store('services', 'public');
                        $type = str_contains($file->getClientMimeType(), 'video') ? 'video' : 'image';
                        
                        ServiceMedia::create([
                            'service_id' => $service->id,
                            'type' => $type,
                            'path' => $path,
                        ]);
                    }
                }

                // Handle sub-services
                if (!empty($data['sub_services'])) {
                    foreach ($data['sub_services'] as $subServiceData) {
                        SubService::create([
                            'service_id' => $service->id,
                            'name' => $subServiceData['name'],
                            'price_from' => $subServiceData['price_from'] ?? null,
                            'price_to' => $subServiceData['price_to'] ?? null,
                        ]);
                    }
                }

                return $service->load(['media', 'subServices', 'store','serviceCategory']);
            });

            return ResponseHelper::success($service, 'Service created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
