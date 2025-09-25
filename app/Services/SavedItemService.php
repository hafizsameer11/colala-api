<?php 


namespace App\Services;

use App\Models\SavedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavedItemService {
    public function toggle(int $userId, int $productId): array {
        $saved = SavedItem::where('user_id',$userId)->where('product_id',$productId)->first();

        if ($saved) {
            $saved->delete();
            return ['saved' => false];
        }

        $new = SavedItem::create([
            'user_id'=>$userId,
            'product_id'=>$productId
        ]);

        return ['saved' => true, 'id'=>$new->id];
    }

    public function list(int $userId) {
        return SavedItem::with('product.images')
            ->where('user_id',$userId)
            ->latest()
            ->get()
            ->map(function($saved){
                return [
                    'id'=>$saved->id,
                    'product_id'=>$saved->product_id,
                    'name'=>$saved->product->name,
                    'price'=>$saved->product->price,
                    'discount_price'=>$saved->product->discount_price,
                    'images'=>$saved->product->images->map(fn($img)=>asset('storage/'.$img->path)),
                    'saved_at'=>$saved->created_at
                ];
            });
    }

    public function isSaved(int $userId, int $productId): bool {
        return SavedItem::where('user_id',$userId)->where('product_id',$productId)->exists();
    }
}
