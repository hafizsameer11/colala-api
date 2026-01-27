<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminUserManagementController extends Controller
{
    protected $userService;
    protected $walletService;

    public function __construct(UserService $userService, WalletService $walletService)
    {
        $this->userService = $userService;
        $this->walletService = $walletService;
    }

    /**
     * Get all admin users with pagination and search
     */
    public function index(Request $request)
    {
        try {
            // Remove global scope to get ALL users (including those with visibility=0)
            $query = User::withoutGlobalScopes()->with('wallet');
            // Returns ALL users: sellers, buyers, admins, null roles, etc.

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Role filter - optional, can filter by specific role if needed
            if ($request->has('role') && $request->role !== 'all') {
                if ($request->role === 'null' || $request->role === 'NULL') {
                    $query->whereNull('role');
                } else {
                    $query->where('role', $request->role);
                }
            }

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('is_active', $request->status === 'active');
            }

            $users = $query->latest()->paginate(15);

            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'user_name' => $user->user_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_picture' => $user->profile_picture,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'wallet_balance' => $user->wallet ? number_format($user->wallet->shopping_balance + $user->wallet->reward_balance, 2) : '0.00',
                    'created_at' => $user->created_at ? $user->created_at->format('d-m-Y H:i:s') : null
                ];
            });

            return ResponseHelper::success($users, 'Admin users retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get admin user statistics
     */
    public function stats()
    {
        try {
            $totalAdmins = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->count();
            $activeAdmins = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->where('is_active', true)->count();
            $newAdmins = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->where('created_at', '>=', now()->subMonth())->count();

            // Calculate percentage increase (mock data for now)
            $totalIncrease = 5;
            $activeIncrease = 5;
            $newIncrease = 5;

            $stats = [
                'total_admins' => [
                    'value' => $totalAdmins,
                    'increase' => $totalIncrease,
                    'icon' => 'users',
                    'color' => 'red'
                ],
                'active_admins' => [
                    'value' => $activeAdmins,
                    'increase' => $activeIncrease,
                    'icon' => 'users',
                    'color' => 'red'
                ],
                'new_admins' => [
                    'value' => $newAdmins,
                    'increase' => $newIncrease,
                    'icon' => 'users',
                    'color' => 'red'
                ]
            ];

            return ResponseHelper::success($stats, 'Admin user statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Search admin users
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'search' => 'required|string|min:2'
            ]);

            $search = $request->search;
            $users = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->with('wallet')
                ->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                })
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'user_name' => $user->user_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'profile_picture' => $user->profile_picture,
                        'role' => $user->role,
                        'wallet_balance' => $user->wallet ? number_format($user->wallet->shopping_balance + $user->wallet->reward_balance, 2) : '0.00'
                    ];
                });

            return ResponseHelper::success($users, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on admin users
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'user_ids' => 'required|array',
                'action' => 'required|string|in:activate,deactivate,delete'
            ]);

            $userIds = $request->user_ids;
            $action = $request->action;

            if ($action === 'activate') {
                User::whereIn('role', ['admin', 'moderator', 'super_admin'])->whereIn('id', $userIds)->update(['is_active' => true]);
                $message = "Admin users activated successfully";
            } elseif ($action === 'deactivate') {
                User::whereIn('role', ['admin', 'moderator', 'super_admin'])->whereIn('id', $userIds)->update(['is_active' => false]);
                $message = "Admin users deactivated successfully";
            } else {
                User::whereIn('role', ['admin', 'moderator', 'super_admin'])->whereIn('id', $userIds)->delete();
                $message = "Admin users deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get admin user profile with wallet balances and recent activities
     */
    public function showProfile($id)
    {
        try {
            $user = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->with(['wallet', 'userActivities' => function($query) {
                $query->latest()->limit(10);
            }])->findOrFail($id);

            $profileData = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'state' => $user->state,
                'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                'role' => $user->role,
                'last_login' => $user->updated_at->format('d/m/y - h:i A'),
                'account_created_at' => $user->created_at->format('d/m/y - h:i A'),
                'loyalty_points' => $user->wallet ? $user->wallet->loyality_points : 0,
                'is_blocked' => !$user->is_active,
                'wallet' => [
                    'shopping_balance' => $user->wallet ? number_format($user->wallet->shopping_balance, 0) : '0',
                    'escrow_balance' => $user->wallet ? number_format($user->wallet->referral_balance, 0) : '0'
                ],
                'recent_activities' => $user->userActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'description' => $activity->message,
                        'created_at' => $activity->created_at->format('d/m/y - h:i A')
                    ];
                })
            ];

            return ResponseHelper::success($profileData, 'Admin user profile retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get comprehensive admin user details
     */
    public function userDetails($id)
    {
        try {
            $user = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->with(['wallet'])
                ->findOrFail($id);

            $userData = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'user_name' => $user->user_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_picture' => $user->profile_picture,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'country' => $user->country,
                'state' => $user->state,
                'user_code' => $user->user_code,
                'referral_code' => $user->referral_code,
                'wallet' => $user->wallet ? [
                    'shopping_balance' => number_format($user->wallet->shopping_balance, 2),
                    'reward_balance' => number_format($user->wallet->reward_balance, 2),
                    'referral_balance' => number_format($user->wallet->referral_balance, 2),
                    'loyalty_points' => $user->wallet->loyality_points
                ] : null,
                'created_at' => $user->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $user->updated_at->format('d-m-Y H:i:s')
            ];

            return ResponseHelper::success($userData, 'Admin user details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create new admin user
     */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|max:255',
                'user_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20',
                'password' => 'required|string|min:6',
                'country' => 'required|string',
                'state' => 'required|string',
                'role' => 'required|in:admin,moderator,super_admin',
                'referral_code' => 'nullable|string',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $data = $request->all();
            $data['password'] = Hash::make($data['password']);
            $data['user_code'] = $this->userService->createUserCode();
            $data['is_active'] = true;

            // Use the same user service as registration
            $user = $this->userService->create($data);
            
            // Create wallet for user (same as registration)
            $wallet = $this->walletService->create(['user_id' => $user->id]);

            return ResponseHelper::success($user, 'Admin user created successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update admin user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'full_name' => 'sometimes|string|max:255',
                'user_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'phone' => 'sometimes|string|max:20',
                'password' => 'sometimes|string|min:8',
                'role' => 'sometimes|in:admin,moderator,super_admin',
                'is_active' => 'sometimes|boolean',
                'country' => 'nullable|string',
                'state' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $updateData = $request->all();
            
            if (isset($updateData['password'])) {
                $updateData['password'] = Hash::make($updateData['password']);
            }

            $user->update($updateData);

            return ResponseHelper::success($user->fresh(), 'Admin user updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete admin user
     */
    public function delete($id)
    {
        try {
            $user = User::whereIn('role', ['admin', 'moderator', 'super_admin'])->findOrFail($id);
            $user->delete();

            return ResponseHelper::success(null, 'Admin user deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
