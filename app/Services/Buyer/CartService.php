<?php

namespace App\Services\Buyer;

use App\Helpers\ProductStatHelper;
use App\Helpers\BoostMetricsHelper;
use App\Models\{Cart, CartItem, Product, ProductVariant, Coupon, LoyaltyPoint, Wallet};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreateCart(int $userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId, 'checked_out' => false]);
    }

    public function show(Cart $cart): array
    {
        $cart->load(['items.product.images', 'items.variant', 'items.store']);
    
        // resolve default address id (optional); fallback to null if none
        $addressId = \App\Models\UserAddress::where('user_id', $cart->user_id)
            ->where(function ($q) { $q->where('is_default', true)->orWhere('is_default', 1); })
            ->value('id');
    
        $grouped = $cart->items->groupBy('store_id')->map(function ($items) use ($addressId) {
            $subtotal = 0;
    
            $lines = $items->map(function ($i) use (&$subtotal, $addressId) {
                $basePrice = $i->variant?->price ?? $i->product->price;
                $couponDiscountedPrice = $i->unit_discount_price
                    ?? $i->variant?->discount_price
                    ?? $i->product->discount_price
                    ?? $basePrice;
                $pointsDiscount = $i->loyality_points ?? 0;
                $finalUnitPrice = max(0, $couponDiscountedPrice - $pointsDiscount);
                $lineTotal = $finalUnitPrice * $i->qty;
                $subtotal += $lineTotal;
    
                // ✅ per-item shipping fee (address-aware; falls back to product default)
                $deliveryFee = (float) ($i->product?->getDeliveryFee($addressId) ?? 0.0);
    
                return [
                    'id'               => $i->id,
                    'product_id'       => $i->product_id,
                    'variant_id'       => $i->variant_id,
                    'name'             => $i->product->name,
                    'img'              => $i->product->images->first()->image ?? null,
                    'color'            => $i->variant->color ?? null,
                    'size'             => $i->variant->size ?? null,
                    'store_id'         => $i->store_id,
                    'unit_price'       => $basePrice,
                    'discount_price'   => $finalUnitPrice,
                    'coupon_discount'  => $basePrice - $couponDiscountedPrice,
                    'points_discount'  => $pointsDiscount,
                    'qty'              => $i->qty,
                    'line_total'       => $lineTotal,
    
                    // 🔹 only new field added to the item line:
                    'delivery_fee'     => $deliveryFee,
    
                    'product'          => $i->product,
                    'variant'          => $i->variant,
                    'store'            => $i->store,
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
    




    public function add(int $userId, array $payload): Cart
    {
        return DB::transaction(function () use ($userId, $payload) {
            $cart = $this->getOrCreateCart($userId);

            $product = Product::findOrFail($payload['product_id']);
            $variant = isset($payload['variant_id'])
                ? ProductVariant::where('product_id', $product->id)->findOrFail($payload['variant_id'])
                : null;

            if ($variant && $variant->stock < $payload['qty']) {
                throw ValidationException::withMessages(['qty' => 'Insufficient stock for selected variant.']);
            }

            $item = CartItem::firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
            ]);

            $item->store_id = $product->store_id;
            $item->qty = ($item->exists ? $item->qty : 0) + (int)$payload['qty'];
            $item->unit_price = $variant?->price ?? $product->price;
            $item->unit_discount_price = $variant?->discount_price ?? $product->discount_price;
            $item->save();

            // Record add_to_cart event
            ProductStatHelper::record($product->id, 'add_to_cart');
            
            // Update boost metrics for boosted products (add_to_cart = click)
            BoostMetricsHelper::recordAddToCart($product->id);

            return $cart->fresh('items');
        });
    }

    public function updateQty(CartItem $item, int $qty): CartItem
    {
        if ($qty < 1) $qty = 1;
        $item->update(['qty' => $qty]);
        return $item->fresh();
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
    }
    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }
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

        // Find active coupon by code, optionally scoped to the product's store
        $coupon = Coupon::active()
            ->where('code', $data['coupon_code'])
            ->when(isset($product->store_id), function ($q) use ($product) {
                return $q->where(function ($qq) use ($product) {
                    $qq->whereNull('store_id')->orWhere('store_id', $product->store_id);
                });
            })
            ->first();

        if (!$coupon) {
            throw ValidationException::withMessages(['coupon_code' => 'Invalid or expired coupon code.']);
        }

        $unitPrice = $item->unit_price ?? $item->variant?->price ?? $product->price;

        // Compute discount amount from coupon settings
        $discountAmount = 0;
        if (in_array(strtolower($coupon->discount_type), ['percent', 'percentage'])) {
            $discountAmount = (int) floor(($unitPrice * (float)$coupon->discount_value) / 100);
        } else {
            // fixed amount
            $discountAmount = (int) $coupon->discount_value;
        }

        $discountAmount = max(0, min($discountAmount, $unitPrice));

        $item->unit_discount_price = max(0, $unitPrice - $discountAmount);
        $item->discount = $discountAmount;
        // $item->coupon_code = $coupon->code ?? $data['coupon_code'];
        $item->save();

        return $item->fresh();
    }
    public function applyPoints(int $userId, array $data)
    {
        $cart = $this->getOrCreateCart($userId);

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $data['product_id'])
            ->first();

        if (!$item) {
            throw ValidationException::withMessages([
                'product_id' => 'Product not found in cart.'
            ]);
        }

        // Ensure points are numeric
        $points = max(0, (int)$data['points']);

        // Validate user has enough loyalty points for this store
        $storeId = $item->store_id;
        $earnedPointsForStore = (int) LoyaltyPoint::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->sum('points');
        if ($points > $earnedPointsForStore) {
            throw ValidationException::withMessages([
                'points' => 'Insufficient loyalty points for this store.'
            ]);
        }

        $unitPrice = $item->unit_price ?? $item->variant?->price ?? $item->product->price;
        if ($points > $unitPrice) {
            throw ValidationException::withMessages([
                'points' => 'Points cannot exceed product price.'
            ]);
        }

        // Save points and adjust unit_discount_price if coupon or product discount exists
        $baseDiscountPrice = $item->unit_discount_price
            ?? $item->variant?->discount_price
            ?? $item->product->discount_price
            ?? $unitPrice;

        // Cap points to not exceed the current discounted unit price
        $pointsToApply = min($points, (int)$baseDiscountPrice);

        // Ensure wallet has enough global loyalty points; create wallet if missing
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['shopping_balance' => 0, 'reward_balance' => 0, 'referral_balance' => 0, 'loyality_points' => 0]
        );
        $availableWalletPoints = (int) $wallet->loyality_points;
        $pointsToApply = min($pointsToApply, $availableWalletPoints);

        // Apply points once (not per quantity); 1 point == 1 naira
        $item->loyality_points = $pointsToApply;
        $item->unit_discount_price = max(0, $baseDiscountPrice - $pointsToApply);
        $item->save();

        // Deduct applied points from wallet balance
        if ($pointsToApply > 0) {
            $wallet->decrement('loyality_points', $pointsToApply);
        }

        return $item->fresh();
    }
}
