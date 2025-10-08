<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreUser;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreUserService
{
    /**
     * Get all users for a store
     */
    public function getStoreUsers(int $storeId): array
    {
        $store = Store::where('user_id', Auth::id())->findOrFail($storeId);
        
        return StoreUser::with(['user:id,full_name,email,profile_picture'])
            ->where('store_id', $storeId)
            ->get()
            ->map(function ($storeUser) {
                return [
                    'id' => $storeUser->id,
                    'user_id' => $storeUser->user_id,
                    'name' => $storeUser->user->full_name,
                    'email' => $storeUser->user->email,
                    'profile_picture' => $storeUser->user->profile_picture,
                    'role' => $storeUser->role,
                    'permissions' => $storeUser->getAllPermissions(),
                    'is_active' => $storeUser->is_active,
                    'invited_at' => $storeUser->invited_at,
                    'joined_at' => $storeUser->joined_at,
                    'invited_by' => $storeUser->inviter?->full_name,
                ];
            })
            ->toArray();
    }

    /**
     * Invite a new user to the store
     */
    public function inviteUser(int $storeId, array $data): array
    {
        $store = Store::where('user_id', Auth::id())->findOrFail($storeId);
        
        // Check if user already exists
        $existingUser = User::where('email', $data['email'])->first();
        
        if ($existingUser) {
            // Check if user is already in this store
            $existingStoreUser = StoreUser::where('store_id', $storeId)
                ->where('user_id', $existingUser->id)
                ->first();
                
            if ($existingStoreUser) {
                throw ValidationException::withMessages([
                    'email' => 'User is already a member of this store.'
                ]);
            }
        }

        return DB::transaction(function () use ($storeId, $data, $existingUser, $store) {
            // Create user if doesn't exist
            if (!$existingUser) {
                $user = User::create([
                    'full_name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'user_code' => 'USR' . Str::random(8),
                ]);
            } else {
                $user = $existingUser;
            }

            // Create store user relationship
            $storeUser = StoreUser::create([
                'store_id' => $storeId,
                'user_id' => $user->id,
                'role' => $data['role'],
                'permissions' => $data['permissions'] ?? [],
                'invited_at' => now(),
                'invited_by' => Auth::id(),
            ]);

            // Send invitation email (optional)
            $this->sendInvitationEmail($user, $store);

            return [
                'id' => $storeUser->id,
                'user_id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $storeUser->role,
                'permissions' => $storeUser->getAllPermissions(),
                'invited_at' => $storeUser->invited_at,
            ];
        });
    }

    /**
     * Update user role and permissions
     */
    public function updateUser(int $storeId, int $storeUserId, array $data): array
    {
        $store = Store::where('user_id', Auth::id())->findOrFail($storeId);
        
        $storeUser = StoreUser::where('store_id', $storeId)
            ->where('id', $storeUserId)
            ->firstOrFail();

        $storeUser->update([
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? [],
            'is_active' => $data['is_active'] ?? $storeUser->is_active,
        ]);

        return [
            'id' => $storeUser->id,
            'user_id' => $storeUser->user_id,
            'role' => $storeUser->role,
            'permissions' => $storeUser->getAllPermissions(),
            'is_active' => $storeUser->is_active,
        ];
    }

    /**
     * Remove user from store
     */
    public function removeUser(int $storeId, int $storeUserId): bool
    {
        $store = Store::where('user_id', Auth::id())->findOrFail($storeId);
        
        $storeUser = StoreUser::where('store_id', $storeId)
            ->where('id', $storeUserId)
            ->firstOrFail();

        // Prevent removing store owner
        if ($storeUser->user_id === $store->user_id) {
            throw new Exception('Cannot remove store owner from the store.');
        }

        return $storeUser->delete();
    }

    /**
     * Get user's stores
     */
    public function getUserStores(int $userId): array
    {
        return StoreUser::with(['store:id,store_name,store_email,profile_image'])
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get()
            ->map(function ($storeUser) {
                return [
                    'id' => $storeUser->id,
                    'store_id' => $storeUser->store_id,
                    'store_name' => $storeUser->store->store_name,
                    'store_email' => $storeUser->store->store_email,
                    'profile_image' => $storeUser->store->profile_image,
                    'role' => $storeUser->role,
                    'permissions' => $storeUser->getAllPermissions(),
                    'joined_at' => $storeUser->joined_at,
                ];
            })
            ->toArray();
    }

    /**
     * Join store (for invited users)
     */
    public function joinStore(int $storeId, int $userId): array
    {
        $storeUser = StoreUser::where('store_id', $storeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($storeUser->joined_at) {
            throw new Exception('User has already joined this store.');
        }

        $storeUser->update([
            'joined_at' => now(),
            'is_active' => true,
        ]);

        return [
            'id' => $storeUser->id,
            'store_id' => $storeUser->store_id,
            'role' => $storeUser->role,
            'permissions' => $storeUser->getAllPermissions(),
            'joined_at' => $storeUser->joined_at,
        ];
    }

    /**
     * Check if user has permission for store
     */
    public function hasStorePermission(int $userId, int $storeId, string $permission): bool
    {
        $storeUser = StoreUser::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->first();

        if (!$storeUser) {
            return false;
        }

        return $storeUser->hasPermission($permission);
    }

    /**
     * Get available roles
     */
    public function getAvailableRoles(): array
    {
        return [
            'admin' => [
                'name' => 'Admin',
                'description' => 'Full access to all store features',
                'permissions' => [
                    'manage_products',
                    'manage_orders',
                    'manage_customers',
                    'manage_analytics',
                    'manage_settings',
                    'manage_users',
                    'manage_inventory',
                    'manage_promotions',
                ]
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Access to most store features except user management',
                'permissions' => [
                    'manage_products',
                    'manage_orders',
                    'manage_customers',
                    'manage_analytics',
                    'manage_inventory',
                    'manage_promotions',
                ]
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'Basic access to orders and customers',
                'permissions' => [
                    'manage_orders',
                    'manage_customers',
                    'view_analytics',
                ]
            ],
        ];
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail(User $user, Store $store): void
    {
        // This would typically send an email notification
        // For now, we'll just log it
        Log::info("Invitation sent to {$user->email} for store {$store->store_name}");
    }
}
