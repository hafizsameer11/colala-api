<?php 

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;

class ProductVariantService
{
    public function create($productId, $data)
    {
        $product = Product::findOrFail($productId);
        $variant = $product->variants()->create($data);

        if (isset($data['images'])) {
            foreach ($data['images'] as $file) {
                $path = $file->store('products', 'public');
                ProductImage::create([
                    'product_id' => $productId,
                    'variant_id' => $variant->id,
                    'path' => $path
                ]);
            }
        }
        
        // Update product quantity after adding variant
        $product->updateQuantityFromVariants();

        return $variant->load('images');
    }

    public function update($productId, $variantId, $data)
    {
        $product = Product::findOrFail($productId);
        $variant = ProductVariant::where('product_id', $productId)->findOrFail($variantId);
        $variant->update($data);

        if (isset($data['images'])) {
            foreach ($data['images'] as $file) {
                $path = $file->store('products', 'public');
                ProductImage::create([
                    'product_id' => $productId,
                    'variant_id' => $variant->id,
                    'path' => $path
                ]);
            }
        }
        
        // Update product quantity after updating variant
        $product->updateQuantityFromVariants();

        return $variant->load('images');
    }

    public function delete($productId, $variantId)
    {
        $product = Product::findOrFail($productId);
        $variant = ProductVariant::where('product_id', $productId)->findOrFail($variantId);
        $result = $variant->delete();
        
        // Update product quantity after deleting variant
        $product->updateQuantityFromVariants();
        
        return $result;
    }
}
