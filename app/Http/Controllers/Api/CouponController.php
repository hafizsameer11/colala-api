<?php

// app/Http/Controllers/Api/CouponController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\CouponService;
use Exception;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    private CouponService $service;

    public function __construct(CouponService $service) {
        $this->service = $service;
    }

    protected function userStore(): Store {
        $store = Store::where('user_id', Auth::id())->first();
        if(!$store){
            $storeUser = StoreUser::where('user_id', Auth::user()->id)->first();
            if($storeUser){
                $store = $storeUser->store;
            }
        }
        if(!$store){
            throw new Exception('Store not found');
        }
        return $store;
    }

    public function index() {
        $store = $this->userStore();
        $coupons = Coupon::where('store_id',$store->id)->latest()->get();
        return ResponseHelper::success(CouponResource::collection($coupons));
    }

    public function store(CouponRequest $request) {
        try {
            $coupon = $this->service->create($this->userStore(), $request->validated());
            return ResponseHelper::success(new CouponResource($coupon),"Coupon created");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed: ".$e->getMessage());
        }
    }

    public function update(CouponRequest $request, Coupon $coupon) {
        try {
            $this->authorizeCoupon($coupon);
            $coupon = $this->service->update($coupon, $request->validated());
            return ResponseHelper::success(new CouponResource($coupon),"Coupon updated");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed: ".$e->getMessage());
        }
    }

    public function destroy(Coupon $coupon) {
        try {
            $this->authorizeCoupon($coupon);
            $this->service->delete($coupon);
            return ResponseHelper::success(null,"Coupon deleted");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed: ".$e->getMessage());
        }
    }

    public function apply($code) {
        try {
            $result = $this->service->applyCoupon($code, Auth::id(), 10000); // pass order amount dynamically
            return ResponseHelper::success($result,"Coupon applied");
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    protected function authorizeCoupon(Coupon $coupon) {
        $store = $this->userStore();
        abort_if($coupon->store_id !== $store->id, 403, "Unauthorized coupon access");
    }
}
