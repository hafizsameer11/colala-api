<?php
namespace App\Services\Buyer;

use App\Models\{Cart, CartItem, Product, ProductVariant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService {
    public function getOrCreateCart(int $userId): Cart {
        return Cart::firstOrCreate(['user_id'=>$userId,'checked_out'=>false]);
    }

   public function show(Cart $cart): array
{
    $cart->load(['items.product.images','items.variant','items.store']);

    $grouped = $cart->items->groupBy('store_id')->map(function ($items) {
        $subtotal = 0;

        $lines = $items->map(function ($i) use (&$subtotal) {
            // actual product base price
            $actualPrice = $i->variant?->price ?? $i->product->price;

            // product-level discount (if any)
            $productDiscountPrice = $i->variant?->discount_price ?? $i->product->discount_price;

            // coupon-based discount (if applied)
            $couponDiscount = $i->discount ?? 0;

            // Final unit price after discounts/coupon
            $finalUnitPrice = $productDiscountPrice
                ? $productDiscountPrice - $couponDiscount
                : $actualPrice - $couponDiscount;

            // Ensure not negative
            $finalUnitPrice = max(0, $finalUnitPrice);

            $lineTotal = $finalUnitPrice * $i->qty;
            $subtotal += $lineTotal;

            return [
                'id'                => $i->id,
                'product_id'        => $i->product_id,
                'variant_id'        => $i->variant_id,
                'name'              => $i->product->name,
                'img'               => $i->product->images->first()->image ?? null,
                'color'             => $i->variant->color ?? null,
                'size'              => $i->variant->size ?? null,
                'store_id'          => $i->store_id,

                // 👉 Show base price and final price separately
                'unit_price'        => $actualPrice,
                'discount_price'    => $finalUnitPrice,

                'qty'               => $i->qty,
                'line_total'        => $lineTotal,

                'product'           => $i->product,
                'variant'           => $i->variant,
                'store'             => $i->store,
            ];
        });

        return [
            'items'          => $lines->values(),
            'items_subtotal' => $subtotal,
        ];
    });

    $itemsTotal = $grouped->sum('items_subtotal');

    return [
        'stores'      => $grouped,
        'items_total' => $itemsTotal,
    ];
}


    public function add(int $userId, array $payload): Cart {
        return DB::transaction(function() use ($userId, $payload) {
            $cart = $this->getOrCreateCart($userId);

            $product = Product::findOrFail($payload['product_id']);
            $variant = isset($payload['variant_id'])
                ? ProductVariant::where('product_id',$product->id)->findOrFail($payload['variant_id'])
                : null;

            if ($variant && $variant->stock < $payload['qty']) {
                throw ValidationException::withMessages(['qty' => 'Insufficient stock for selected variant.']);
            }

            $item = CartItem::firstOrNew([
                'cart_id'=>$cart->id,
                'product_id'=>$product->id,
                'variant_id'=>$variant?->id,
            ]);

            $item->store_id = $product->store_id;
            $item->qty = ($item->exists ? $item->qty : 0) + (int)$payload['qty'];
            $item->unit_price = $variant?->price ?? $product->price;
            $item->unit_discount_price = $variant?->discount_price ?? $product->discount_price;
            $item->save();

            return $cart->fresh('items');
        });
    }

    public function updateQty(CartItem $item, int $qty): CartItem {
        if ($qty < 1) $qty = 1;
        $item->update(['qty'=>$qty]);
        return $item->fresh();
    }

    public function remove(CartItem $item): void { $item->delete(); }
    public function clear(Cart $cart): void { $cart->items()->delete(); }
    public function applyCoupon(int $userId, array $data)
    {
        $cart = $this->getOrCreateCart($userId);
        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $data['product_id'])
            ->first();

        if (!$item) {
            throw ValidationException::withMessages(['product_id' => 'Product not found in cart.']);
        }

        $product = Product::findOrFail($data['product_id']);

        if ($product->coupon_code !== $data['coupon_code']) {
            throw ValidationException::withMessages(['coupon_code' => 'Invalid coupon code for this product.']);
        }

        // Assuming a fixed discount for simplicity; this could be more complex
        $discountAmount = $product->discount; 
        $item->unit_discount_price = max(0, ($item->unit_price ?? $product->price) - $discountAmount);
        $item->discount = $discountAmount;
        $item->save();

        return $item->fresh();
    }
}
