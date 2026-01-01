<?php

namespace App\Traits;

use App\Models\Store;
use App\Models\StoreUser;

trait StoreAccessTrait
{
    /**
     * Get store for authenticated user (supports both owner and StoreUser)
     * 
     * @param \App\Models\User $user
     * @return Store
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function getStore($user): Store
    {
        // Try to get store from user's direct relationship (store owner)
        $store = $user->store;
        
        // If user is not the owner, check StoreUser table (staff/manager)
        if (!$store) {
            $storeUser = StoreUser::where('user_id', $user->id)->first();
            if ($storeUser) {
                $store = $storeUser->store;
            } else {
                abort(404, 'Store not found');
            }
        }
        
        return $store;
    }

    /**
     * Get store ID for authenticated user (supports both owner and StoreUser)
     * 
     * @param \App\Models\User $user
     * @return int
     * @throws \Exception
     */
    protected function getStoreId($user): int
    {
        $store = $this->getStore($user);
        return $store->id;
    }
}

