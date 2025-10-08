<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimpleStoreUserService
{
    /**
     * Get all users for a store
     */
    public function getStoreUsers(int $storeId): array
    {
        $store = Store::where('user_id', Auth::id())->findOrFail($storeId);
        
        return User::where('store_id', $storeId)
            ->select(['id', 'full_name', 'email', 'profile_picture', 'created_at'])
            ->get()
            ->map(function ($user) use ($store) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'profile_picture' => $user->profile_picture,
                    'joined_at' => $user->created_at,
                    'is_owner' => $user->id === $store->user_id,
                ];
            })
            ->toArray();
    }

    /**
     * Add user to store
     */
    public function addUserToStore(int $storeId, array $data): array
    {
        $store = Store::where('user_id', Auth::id())->findOrFail($storeId);
        
        // Check if user already exists
        $existingUser = User::where('email', $data['email'])->first();
        
        if ($existingUser) {
            // Check if user is already in a store
            if ($existingUser->store_id) {
                throw ValidationException::withMessages([
                    'email' => 'User is already associated with a store.'
                ]);
            }
            
            // Update existing user with store
            $existingUser->update(['store_id' => $storeId]);
            $user = $existingUser;
        } else {
            // Create new user
            $user = User::create([
                'full_name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_code' => 'USR' . Str::random(8),
                'store_id' => $storeId,
            ]);
        }

        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'store_id' => $user->store_id,
            'joined_at' => $user->created_at,
        ];
    }

    /**
     * Remove user from store
     */
    public function removeUserFromStore(int $storeId, int $userId): bool
    {
        $store = Store::where('user_id', Auth::id())->findOrFail($storeId);
        
        // Prevent removing store owner
        if ($userId === $store->user_id) {
            throw new Exception('Cannot remove store owner from the store.');
        }

        $user = User::where('store_id', $storeId)
            ->where('id', $userId)
            ->firstOrFail();

        // Remove user from store
        $user->update(['store_id' => null]);

        return true;
    }

    /**
     * Get user's store information
     */
    public function getUserStore(int $userId): ?array
    {
        $user = User::with('store')->findOrFail($userId);
        
        if (!$user->store_id) {
            return null;
        }

        return [
            'store_id' => $user->store_id,
            'store_name' => $user->store->store_name,
            'store_email' => $user->store->store_email,
            'profile_image' => $user->store->profile_image,
            'is_owner' => $user->id === $user->store->user_id,
        ];
    }

    /**
     * Assign store to user (for seller registration)
     */
    public function assignStoreToUser(int $userId, int $storeId): bool
    {
        $user = User::findOrFail($userId);
        $user->update(['store_id' => $storeId]);
        
        return true;
    }

    /**
     * Check if user is seller
     */
    public function isUserSeller(int $userId): bool
    {
        $user = User::findOrFail($userId);
        return !is_null($user->store_id);
    }

    /**
     * Get store owner
     */
    public function getStoreOwner(int $storeId): ?User
    {
        $store = Store::findOrFail($storeId);
        return $store->user;
    }

    /**
     * Get all store users including owner
     */
    public function getAllStoreUsers(int $storeId): array
    {
        $store = Store::with('user')->findOrFail($storeId);
        $users = User::where('store_id', $storeId)->get();
        
        $allUsers = collect();
        
        // Add store owner
        $allUsers->push([
            'id' => $store->user->id,
            'name' => $store->user->full_name,
            'email' => $store->user->email,
            'profile_picture' => $store->user->profile_picture,
            'is_owner' => true,
            'joined_at' => $store->user->created_at,
        ]);
        
        // Add other users
        foreach ($users as $user) {
            $allUsers->push([
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'profile_picture' => $user->profile_picture,
                'is_owner' => false,
                'joined_at' => $user->created_at,
            ]);
        }
        
        return $allUsers->toArray();
    }
}
