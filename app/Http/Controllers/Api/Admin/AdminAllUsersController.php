<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminAllUsersController extends Controller
{
    /**
     * Get all users with filtering and pagination
     */
    public function getAllUsers(Request $request)
    {
        try {
            $query = User::with(['wallet', 'store']);

            // Apply filters
            if ($request->has('user_type') && $request->user_type !== 'all') {
                $query->where('role', $request->user_type);
            }

            if ($request->has('date_range')) {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                        break;
                }
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $users = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_users' => User::count(),
                'buyer_users' => User::where('role', 'buyer')->count(),
                'seller_users' => User::where('role', 'seller')->count(),
                'active_users' => User::where('status', 'active')->count(),
                'inactive_users' => User::where('status', 'inactive')->count(),
            ];

            return ResponseHelper::success([
                'users' => $this->formatUsersData($users),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed user information
     */
    public function getUserDetails($userId)
    {
        try {
            $user = User::with([
                'wallet',
                'store',
                'orders',
                'transactions',
                'loyaltyPoints','userActivities'
            ])->findOrFail($userId);

            $userData = [
                'user_info' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_name' => $user->user_name,
                    'country' => $user->country,
                    'state' => $user->state,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_picture' => $user->profile_picture,
                    'user_code' => $user->user_code,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'wallet_info' => $user->wallet ? [
                    'id' => $user->wallet->id,
                    'balance' => $user->wallet->balance,
                    'escrow_balance' => $user->wallet->escrow_balance,
                    'points_balance' => $user->wallet->points_balance,
                    'created_at' => $user->wallet->created_at,
                ] : null,
                'store_info' => $user->store ? [
                    'store_id' => $user->store->id,
                    'store_name' => $user->store->store_name,
                    'store_email' => $user->store->store_email,
                    'store_phone' => $user->store->store_phone,
                    'store_location' => $user->store->store_location,
                    'status' => $user->store->status,
                    'created_at' => $user->store->created_at,
                ] : null,
                'statistics' => [
                    'total_orders' => $user->orders->count(),
                    'total_transactions' => $user->transactions->count(),
                    'total_loyalty_points' => $user->loyaltyPoints->sum('points'),
                    'total_spent' => $user->orders->sum('grand_total'),
                    'average_order_value' => $user->orders->avg('grand_total') ?? 0,
                ],
                'recent_orders' => $user->orders()->latest()->limit(5)->get()->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_no' => $order->order_no,
                        'grand_total' => $order->grand_total,
                        'payment_status' => $order->payment_status,
                        'created_at' => $order->created_at,
                        'formatted_date' => $order->created_at->format('d-m-Y H:i A'),
                    ];
                }),
                'activities' => $user->userActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'activity' => $activity->message,
                        'created_at' => $activity->created_at,
                    ];
                }),
                'recent_transactions' => $user->transactions()->latest()->limit(5)->get()->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'tx_id' => $transaction->tx_id,
                        'amount' => $transaction->amount,
                        'status' => $transaction->status,
                        'type' => $transaction->type,
                        'created_at' => $transaction->created_at,
                        'formatted_date' => $transaction->created_at->format('d-m-Y H:i A'),
                    ];
                }),
            ];

            return ResponseHelper::success($userData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, $userId)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive',
            ]);

            $user = User::findOrFail($userId);
            
            $user->update([
                'status' => $request->status,
            ]);

            return ResponseHelper::success([
                'user_id' => $user->id,
                'status' => $user->status,
                'updated_at' => $user->updated_at,
            ], 'User status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user analytics
     */
    public function getUserAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // User registration trends
            $registrationTrends = User::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_users,
                SUM(CASE WHEN role = "buyer" THEN 1 ELSE 0 END) as buyers,
                SUM(CASE WHEN role = "seller" THEN 1 ELSE 0 END) as sellers
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // User activity statistics
            $activityStats = [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'inactive_users' => User::where('status', 'inactive')->count(),
                'buyer_users' => User::where('role', 'buyer')->count(),
                'seller_users' => User::where('role', 'seller')->count(),
                'users_with_orders' => User::whereHas('orders')->count(),
                'users_with_transactions' => User::whereHas('transactions')->count(),
            ];

            return ResponseHelper::success([
                'registration_trends' => $registrationTrends,
                'activity_stats' => $activityStats,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format users data for response
     */
    private function formatUsersData($users)
    {
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'profile_picture' => $user->profile_picture,
                'wallet_balance' => $user->wallet ? $user->wallet->balance : 0,
                'escrow_balance' => $user->wallet ? $user->wallet->escrow_balance : 0,
                'points_balance' => $user->wallet ? $user->wallet->points_balance : 0,
                'store_name' => $user->store ? $user->store->store_name : null,
                'created_at' => $user->created_at,
                'formatted_date' => $user->created_at->format('d-m-Y H:i A'),
            ];
        });
    }

    /**
     * Create new user (admin can add users)
     */
    public function createUser(Request $request)
    {
        try {
            $request->validate([
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20|unique:users,phone',
                'password' => 'required|string|min:8',
                'role' => 'required|in:buyer,seller,admin',
                'status' => 'nullable|in:active,inactive',
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $userData = [
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'status' => $request->status ?? 'active',
            ];

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $profilePath = $request->file('profile_picture')->store('profiles', 'public');
                $userData['profile_picture'] = $profilePath;
            }

            $user = User::create($userData);

            // Create wallet for the user
            $user->wallet()->create([
                'shopping_balance' => 0,
                'reward_balance' => 0,
                'referral_balance' => 0,
                'loyality_points' => 0,
            ]);

            return ResponseHelper::success([
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                    'created_at' => $user->created_at,
                ]
            ], 'User created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update user details (admin can edit user information)
     */
    public function updateUser(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);

            $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $userId,
                'phone' => 'sometimes|string|max:20|unique:users,phone,' . $userId,
                'password' => 'sometimes|string|min:8',
                'role' => 'sometimes|in:buyer,seller,admin',
                'status' => 'sometimes|in:active,inactive',
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $updateData = $request->only(['full_name', 'email', 'phone', 'role', 'status']);

            // Handle password update
            if ($request->has('password')) {
                $updateData['password'] = bcrypt($request->password);
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                // Delete old profile picture if exists
                if ($user->profile_picture) {
                    Storage::disk('public')->delete($user->profile_picture);
                }
                
                $profilePath = $request->file('profile_picture')->store('profiles', 'public');
                $updateData['profile_picture'] = $profilePath;
            }

            $user->update($updateData);

            return ResponseHelper::success([
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                    'updated_at' => $user->updated_at,
                ]
            ], 'User updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete user (admin can remove users)
     */
    public function deleteUser($userId)
    {
        try {
            $user = User::findOrFail($userId);

            // Check if user has any orders or transactions
            $hasOrders = $user->orders()->exists() || $user->storeOrders()->exists();
            $hasTransactions = $user->transactions()->exists();

            if ($hasOrders || $hasTransactions) {
                return ResponseHelper::error('Cannot delete user with existing orders or transactions. Please deactivate instead.', 400);
            }

            // Delete profile picture if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            // Delete user (this will cascade delete related records)
            $user->delete();

            return ResponseHelper::success(null, 'User deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
