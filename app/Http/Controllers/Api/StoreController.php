<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Store;
use App\Models\StoreFollow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function getAll(Request $req) {
        // This is a placeholder implementation. Replace with actual logic to fetch stores.
        try{
            $Stores=Store::latest()->get();
            return ResponseHelper::success($Stores);
        }catch(\Exception $e){
            return ResponseHelper::error( $e->getMessage(), 500);
        }
    }

public function getById(Request $req, $storeId)
{
    try {
        $store = Store::with([
            'user',
            'socialLinks',
            'businessDetails',
            'addresses',
            'deliveryPricing',
            'categories',
            'products.orderItems',
            'products.images',
            'products.variations',
            'services',
            'productReviews.user',
            'productReviews.orderItem',
            'storeReveiews.user',
            'announcements',
            'banners',
            'followers.user' // to get follower count
        ])
        ->withSum([
            'soldItems as total_sold' => function ($q) {
                $q->select(DB::raw('COALESCE(SUM(qty),0)'));
            }
        ], 'qty')
        ->findOrFail($storeId);
        $user=Auth::user();
        $store->posts=Post::where('user_id',$store->user_id)->latest()->get();
        //check current user has followed this store or not
        $store->is_followed =StoreFollow::where('user_id',$user->id)->where('store_id',$storeId)->exists();
        $store->rating=round($store->productReviews->avg('rating'),1) ?? 4.7;

        return ResponseHelper::success($store);
    } catch (\Exception $e) {
        return ResponseHelper::error($e->getMessage(), 500);
    }
}
}
