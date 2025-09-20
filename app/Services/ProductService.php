<?php 


namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\Auth;

class ProductService
{
    public function getAll()
    {
        $storeId=Store::where('user_id',Auth::user()->id)->pluck('id')->first();
        return Product::with(['variants.images','images','deliveryOptions'])
            ->where('store_id', $storeId)
            ->get();
    }

    public function create($data)
    {
        $user=Auth::user();
        $store=Store::where('user_id',$user->id)->first();
        if(!$store){
             throw new Exception('Store not found');
        }
        $data['store_id'] = $store->id;
        $product = Product::create($data);
        // save product images
        if (isset($data['images'])) {
            foreach ($data['images'] as $index => $file) {
                $path = $file->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'is_main' => $index === 0
                ]);
            }
        }
        // link delivery options      
        return $product->load(['images']);
    }

    public function update($id, $data)
    {
        $product = Product::where('store_id', Auth::user()->store->id)->findOrFail($id);
        $product->update($data);

        if (isset($data['images'])) {
            foreach ($data['images'] as $file) {
                $path = $file->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path
                ]);
            }
        }

        if (isset($data['delivery_option_ids'])) {
            $product->deliveryOptions()->sync($data['delivery_option_ids']);
        }

        return $product->load(['images','deliveryOptions']);
    }

    public function delete($id)
    {
        $product = Product::where('store_id', Auth::user()->store->id)->findOrFail($id);
        return $product->delete();
    }
}
