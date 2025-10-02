<?php 
// app/Services/CouponService.php
namespace App\Services;

use App\Models\Coupon;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function create(Store $store, array $data): Coupon {
        $data['store_id'] = $store->id;
        return Coupon::create($data);
    }

    public function update(Coupon $coupon, array $data): Coupon {
        $coupon->update($data);
        return $coupon;
    }

    public function delete(Coupon $coupon): bool {
        return $coupon->delete();
    }

    public function applyCoupon(string $code, int $userId, float $amount): array
    {
        $coupon = Coupon::active()->where('code', $code)->first();
        if(!$coupon) {
            throw new \Exception("Invalid or expired coupon");
        }

        // Check global usage
        if($coupon->times_used >= $coupon->max_usage) {
            throw new \Exception("Coupon usage limit reached");
        }

        // Check per-user usage (you might need a pivot table `coupon_user_usages`)
        // Example simplified: ignore per-user usage for now

        $discount = 0;
        if ($coupon->discount_type == 1) {
            $discount = $amount * ($coupon->discount_value / 100);
        } else {
            $discount = min($amount, $coupon->discount_value);
        }

        // increment usage
        $coupon->increment('times_used');

        return [
            'coupon'    => $coupon->code,
            'discount'  => round($discount, 2),
            'final_amount' => $amount - $discount,
        ];
    }
}
