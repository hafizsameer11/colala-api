<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Store;
use App\Models\StoreFollow;
use App\Models\StoreVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function getAll(Request $req) {
        try{
            $stores = Store::withCount('followers')
                ->withSum(['soldItems as total_sold' => function ($q) { $q; }], 'qty')
                ->latest()
                ->get();
            // expose computed attributes if needed
            $stores->each(function ($store) {
                $store->qty_sold = (int)($store->total_sold ?? 0);
                $store->followers_count = (int)($store->followers_count ?? 0);
            });
            return ResponseHelper::success($stores);
        }catch(\Exception $e){
            return ResponseHelper::error( $e->getMessage(), 500);
        }
    }

public function getById(Request $req, $id)
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
            'services.media',
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
        ->findOrFail($id);
        
        $user=Auth::user();
        $store->posts=Post::where('user_id',$store->user_id)->latest()->get();
        
        //check current user has followed this store or not
        $store->is_followed = $user
        ? StoreFollow::where('user_id', $user->id)->where('store_id', $id)->exists()
        : false;
        
        $store->rating=round($store->productReviews->avg('rating'),1) ?? 4.7;

        // Hide phone number if visibility is disabled
        if (!$store->is_phone_visible) {
            $store->store_phone = null;
        }
        StoreVisitor::create([
            'store_id' => $id,
            'user_id' => $user->id,
            'visit_type' => 'store',
            'ip_address' => $req->ip(),
            'user_agent' => $req->userAgent(),
        ]);

        // Track store visit (must be after store is loaded to ensure store exists)
        // \App\Helpers\VisitorTracker::trackStoreVisit($id, $req);

        return ResponseHelper::success($store);
    } catch (\Exception $e) {
        return ResponseHelper::error($e->getMessage(), 500);
    }
}
}
