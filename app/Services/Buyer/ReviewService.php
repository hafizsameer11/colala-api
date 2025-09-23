<?php 


namespace App\Services\Buyer;

use App\Models\{OrderItem, ProductReview};
use Illuminate\Validation\ValidationException;

class ReviewService {
    public function create(int $userId, OrderItem $item, array $data): ProductReview {
        if ($item->storeOrder->order->user_id !== $userId) abort(403);
        if (!in_array($item->storeOrder->status, ['delivered','funds_released','completed'])) {
            throw ValidationException::withMessages(['status'=>'You can review after delivery.']);
        }
        return ProductReview::create([
            'order_item_id'=>$item->id,
            'user_id'=>$userId,
            'rating'=>$data['rating'],
            'comment'=>$data['comment'] ?? null,
            'images'=>$data['images'] ?? [],
        ]);
    }
}
