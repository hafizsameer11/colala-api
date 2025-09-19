<?php 

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;

class ProductVariantService
{
    public function create($productId, $data)
    {
        $variant = Product::findOrFail($productId)
            ->variants()->create($data);

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

        return $variant->load('images');
    }

    public function update($productId, $variantId, $data)
    {
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

        return $variant->load('images');
    }

    public function delete($productId, $variantId)
    {
        $variant = ProductVariant::where('product_id', $productId)->findOrFail($variantId);
        return $variant->delete();
    }
}
