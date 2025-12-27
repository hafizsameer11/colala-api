<?php 

namespace App\Services\Buyer;

use App\Models\StoreFollow;

class StoreFollowService {
    public function toggle(int $userId, int $storeId): array {
        $follow = StoreFollow::where('user_id',$userId)->where('store_id',$storeId)->first();

        if ($follow) {
            $follow->delete();
            return ['following' => false];
        }

        $new = StoreFollow::create([
            'user_id'=>$userId,
            'store_id'=>$storeId
        ]);

        return ['following' => true, 'id'=>$new->id];
    }

    public function list(int $userId) {
        return StoreFollow::with('store.categories')
            ->where('user_id',$userId)
            ->latest()
            ->get()
            ->filter(function($f) {
                return $f->store !== null;
            })
            ->map(function($f){
                return [
                    'id'=>$f->id,
                    'store_id'=>$f->store_id,
                    'store_name'=>$f->store->store_name ?? null,
                    'store_email'=>$f->store->store_email ?? null,
                    'store_phone'=>$f->store->store_phone ?? null,
                    'profile_image'=>$f->store->profile_image ? asset('storage/'.$f->store->profile_image) : null,
                    'banner_image'=>$f->store->banner_image ? asset('storage/'.$f->store->banner_image) : null,
                    'followed_at'=>$f->created_at,
                    'categories'=>$f->store->categories ?? []
                ];
            })
            ->values();
    }

    public function isFollowing(int $userId, int $storeId): bool {
        return StoreFollow::where('user_id',$userId)->where('store_id',$storeId)->exists();
    }
}
