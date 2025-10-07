<?php


namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductService
{

    public function getAll()
    {
        $storeId = Store::where('user_id', Auth::user()->id)->pluck('id')->first();

        return Product::with(['variants.images', 'images'])
            ->withCount([
                'productStats as views' => fn($q) => $q->where('event_type', 'view'),
                'productStats as impressions' => fn($q) => $q->where('event_type', 'impression'),
                'productStats as clicks' => fn($q) => $q->where('event_type', 'click'),
                'productStats as carts' => fn($q) => $q->where('event_type', 'add_to_cart'),
                'productStats as orders' => fn($q) => $q->where('event_type', 'order'),
                'productStats as chats' => fn($q) => $q->where('event_type', 'chat'),
            ])
            ->where('store_id', $storeId)
            ->get();
    }

    public function getAllforBuyer()
    {
        return Product::with(['variants.images', 'images', 'store'])
            ->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            if (!$store) {
                throw new Exception('Store not found');
            }

            $data['store_id'] = $store->id;

            /** Create main product */
            $product = Product::create($data);
            
            // Set initial quantity based on variants or default to 0
            $initialQuantity = 0;

            /** Upload product-level images */
            if (!empty($data['images'])) {
                foreach ($data['images'] as $index => $file) {
                    $path = $file->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path'       => $path,
                        'is_main'    => $index === 0,
                    ]);
                }
            }

            /** Handle variants (if provided) */
            if (!empty($data['variants'])) {
                foreach ($data['variants'] as $i => $variantData) {
                    // Create variant
                    $variant = ProductVariant::create([
                        'product_id'      => $product->id,
                        'sku'             => $variantData['sku'] ?? null,
                        'color'           => $variantData['color'] ?? null,
                        'size'            => $variantData['size'] ?? null,
                        'price'           => $variantData['price'] ?? null,
                        'discount_price'  => $variantData['discount_price'] ?? null,
                        'stock'           => $variantData['stock'] ?? 0,
                    ]);
                    
                    // Add to total quantity
                    $initialQuantity += $variantData['stock'] ?? 0;

                    // Upload variant images
                    if (!empty($variantData['images'])) {
                        foreach ($variantData['images'] as $file) {
                            $path = $file->store('products', 'public');
                            ProductImage::create([
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'path'       => $path,
                            ]);
                        }
                    }
                }
            }
            
            // Update product quantity
            $product->update(['quantity' => $initialQuantity]);

            return $product->load(['images', 'variants.images']);
        });
    }

    /**
     * Update product and its variants
     */
    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $storeId = Store::where('user_id', Auth::id())->pluck('id')->first();
            $product = Product::where('store_id', $storeId)->findOrFail($id);
            $product->update($data);

            /** Replace or append new product images */
            if (!empty($data['images'])) {
                foreach ($data['images'] as $file) {
                    $path = $file->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path'       => $path,
                    ]);
                }
            }

            /** Variants update logic */
            if (!empty($data['variants'])) {
                foreach ($data['variants'] as $variantData) {
                    // If variant_id present → update existing
                    if (!empty($variantData['id'])) {
                        $variant = ProductVariant::where('product_id', $product->id)
                            ->where('id', $variantData['id'])
                            ->first();

                        if ($variant) {
                            $variant->update([
                                'sku'            => $variantData['sku'] ?? $variant->sku,
                                'color'          => $variantData['color'] ?? $variant->color,
                                'size'           => $variantData['size'] ?? $variant->size,
                                'price'          => $variantData['price'] ?? $variant->price,
                                'discount_price' => $variantData['discount_price'] ?? $variant->discount_price,
                                'stock'          => $variantData['stock'] ?? $variant->stock,
                            ]);
                        }
                    } else {
                        // Create new variant if not exists
                        $variant = ProductVariant::create([
                            'product_id'      => $product->id,
                            'sku'             => $variantData['sku'] ?? null,
                            'color'           => $variantData['color'] ?? null,
                            'size'            => $variantData['size'] ?? null,
                            'price'           => $variantData['price'] ?? null,
                            'discount_price'  => $variantData['discount_price'] ?? null,
                            'stock'           => $variantData['stock'] ?? 0,
                        ]);
                    }

                    /** Handle variant images */
                    if (!empty($variantData['images'])) {
                        foreach ($variantData['images'] as $file) {
                            $path = $file->store('products', 'public');
                            ProductImage::create([
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'path'       => $path,
                            ]);
                        }
                    }
                }
            }
            
            // Update product quantity after variant changes
            $product->updateQuantityFromVariants();

            return $product->load(['images', 'variants.images']);
        });
    }


    public function delete($id)
    {
        $storeId = Store::where('user_id', Auth::user()->id)->pluck('id')->first();

        $product = Product::where('store_id', $storeId)->findOrFail($id);
        return $product->delete();
    }

    public function markAsSold($id)
    {
        $storeId = Store::where('user_id', Auth::user()->id)->pluck('id')->first();
        
        $product = Product::where('store_id', $storeId)->findOrFail($id);
        $product->update([
            'is_sold' => true,
            'is_unavailable' => false, // Reset unavailable when marking as sold
        ]);
        
        return $product->fresh();
    }

    public function markAsUnavailable($id)
    {
        $storeId = Store::where('user_id', Auth::user()->id)->pluck('id')->first();
        
        $product = Product::where('store_id', $storeId)->findOrFail($id);
        $product->update([
            'is_unavailable' => true,
            'is_sold' => false, // Reset sold when marking as unavailable
        ]);
        
        return $product->fresh();
    }

    public function markAsAvailable($id)
    {
        $storeId = Store::where('user_id', Auth::user()->id)->pluck('id')->first();
        
        $product = Product::where('store_id', $storeId)->findOrFail($id);
        $product->update([
            'is_sold' => false, // Reset sold when marking as available
            'is_unavailable' => false, // Reset unavailable when marking as available
        ]);
        
        return $product->fresh();
    }
}
