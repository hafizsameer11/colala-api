<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Escrow;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use App\Models\Store;
use App\Traits\PeriodFilterTrait;
use App\Services\AdminRoleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminAllUsersController extends Controller
{
    use PeriodFilterTrait;

    protected $adminRoleService;

    public function __construct(AdminRoleService $adminRoleService)
    {
        $this->adminRoleService = $adminRoleService;
    }
    /**
     * Get all users with filtering and pagination
     */
    public function getAllUsers(Request $request)
    {
        try {
            $query = User::with(['wallet', 'store']);

            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            // Apply filters
            if ($request->has('user_type') && $request->user_type !== 'all') {
                $query->where('role', $request->user_type);
            }

            // Apply period filter (priority over date_range for backward compatibility)
            if ($period) {
                $this->applyPeriodFilter($query, $period);
            } elseif ($request->has('date_range')) {
                // Legacy support for date_range parameter
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

            // Get summary statistics with period filtering
            $stats = $this->getUserStatistics($period);

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
                'loyaltyPoints',
                'userActivities',
                'addresses'
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
                    'shopping_balance' => $user->wallet->shopping_balance,
                    'reward_balance' => $user->wallet->reward_balance,
                    'referral_balance' => $user->wallet->referral_balance,
                    'loyality_points' => $user->wallet->loyality_points,
                    'escrow_balance' => Escrow::where('user_id', $user->id)->where('status', 'locked')->sum('amount'),
                    'created_at' => $user->wallet->created_at,
                    'updated_at' => $user->wallet->updated_at,
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
                        'formatted_date' => $order->created_at ? $order->created_at->format('d-m-Y H:i A') : null,
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
                        'formatted_date' => $transaction->created_at ? $transaction->created_at->format('d-m-Y H:i A') : null,
                    ];
                }),
                'saved_addresses' => $user->addresses->map(function ($address) {
                    return [
                        'id' => $address->id,
                        'label' => $address->label,
                        'phone' => $address->phone,
                        'line1' => $address->line1,
                        'line2' => $address->line2,
                        'city' => $address->city,
                        'state' => $address->state,
                        'country' => $address->country,
                        'zipcode' => $address->zipcode,
                        'is_default' => $address->is_default,
                        'created_at' => $address->created_at,
                        'formatted_date' => $address->created_at ? $address->created_at->format('d-m-Y H:i A') : null,
                    ];
                }),
            ];

            return ResponseHelper::success($userData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user saved addresses
     */
    public function getUserAddresses($userId)
    {
        try {
            $user = User::with('addresses')->findOrFail($userId);

            $addresses = $user->addresses->map(function ($address) {
                return [
                    'id' => $address->id,
                    'label' => $address->label,
                    'phone' => $address->phone,
                    'line1' => $address->line1,
                    'line2' => $address->line2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'country' => $address->country,
                    'zipcode' => $address->zipcode,
                    'is_default' => $address->is_default,
                    'created_at' => $address->created_at,
                    'formatted_date' => $address->created_at ? $address->created_at->format('d-m-Y H:i A') : null,
                ];
            });

            return ResponseHelper::success([
                'user_id' => $user->id,
                'user_name' => $user->full_name,
                'total_addresses' => $addresses->count(),
                'addresses' => $addresses,
            ]);
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
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            $dateRange = $this->getDateRange($period);

            // Use period if provided, otherwise fall back to date_from/date_to
            if ($dateRange) {
                $dateFrom = $dateRange['start']->format('Y-m-d');
                $dateTo = $dateRange['end']->format('Y-m-d');
            } else {
                $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
                $dateTo = $request->get('date_to', now()->format('Y-m-d'));
            }

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

            // User activity statistics with period filtering
            $activityStats = $this->getUserStatistics($period);

            return ResponseHelper::success([
                'registration_trends' => $registrationTrends,
                'activity_stats' => $activityStats,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'period' => $period ?? 'all_time'
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user statistics with period filtering
     */
    private function getUserStatistics($period = null)
    {
        // Build base queries
        $totalUsersQuery = User::query();
        $buyerUsersQuery = User::where('role', 'buyer');
        $sellerUsersQuery = User::where('role', 'seller');
        $activeUsersQuery = User::where('status', 'active');
        $inactiveUsersQuery = User::where('status', 'inactive');
        $usersWithOrdersQuery = User::whereHas('orders');
        $usersWithTransactionsQuery = User::whereHas('transactions');

        // Apply period filter if provided
        if ($period) {
            $this->applyPeriodFilter($totalUsersQuery, $period);
            $this->applyPeriodFilter($buyerUsersQuery, $period);
            $this->applyPeriodFilter($sellerUsersQuery, $period);
            $this->applyPeriodFilter($activeUsersQuery, $period);
            $this->applyPeriodFilter($inactiveUsersQuery, $period);
            $this->applyPeriodFilter($usersWithOrdersQuery, $period);
            $this->applyPeriodFilter($usersWithTransactionsQuery, $period);
        }

        return [
            'total_users' => $totalUsersQuery->count(),
            'buyer_users' => $buyerUsersQuery->count(),
            'seller_users' => $sellerUsersQuery->count(),
            'active_users' => $activeUsersQuery->count(),
            'inactive_users' => $inactiveUsersQuery->count(),
            'users_with_orders' => $usersWithOrdersQuery->count(),
            'users_with_transactions' => $usersWithTransactionsQuery->count(),
        ];
    }

    /**
     * Format users data for response
     */
    private function formatUsersData($users)
    {
        return $users->map(function ($user) {
            $escrowBalance = 0;
            if ($user->wallet) {
                $escrowBalance = Escrow::where('user_id', $user->id)->where('status', 'locked')->sum('amount');
            }

            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'profile_picture' => $user->profile_picture,
                'shopping_balance' => $user->wallet ? $user->wallet->shopping_balance : 0,
                'reward_balance' => $user->wallet ? $user->wallet->reward_balance : 0,
                'referral_balance' => $user->wallet ? $user->wallet->referral_balance : 0,
                'loyality_points' => $user->wallet ? $user->wallet->loyality_points : 0,
                'escrow_balance' => $escrowBalance,
                'store_name' => $user->store ? $user->store->store_name : null,
                'created_at' => $user->created_at,
                'formatted_date' => $user->created_at ? $user->created_at->format('d-m-Y H:i A') : null,
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
                'role' => 'required|string|in:buyer,seller,admin,moderator,super_admin,support_agent,financial_manager,content_manager,account_officer',
                'status' => 'nullable|in:active,inactive',
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp',
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

            // Assign RBAC role if it's not 'buyer' or 'seller'
            $userRole = $request->role;
            if ($userRole !== 'buyer' && $userRole !== 'seller') {
                try {
                    $rbacRole = \App\Models\Role::where('slug', $userRole)->where('is_active', true)->first();
                    if ($rbacRole) {
                        $this->adminRoleService->assignRole(
                            $user->id,
                            $rbacRole->id,
                            $request->user()?->id
                        );
                        Log::info("RBAC role '{$userRole}' assigned to user {$user->id}");
                    } else {
                        Log::warning("RBAC role '{$userRole}' not found. Please run the seeder first.");
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to assign RBAC role to user: ' . $e->getMessage(), [
                        'user_id' => $user->id,
                        'role' => $userRole,
                        'error' => $e->getMessage()
                    ]);
                }
            }

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
                'role' => 'sometimes|string|in:buyer,seller,admin,moderator,super_admin,support_agent,financial_manager,content_manager',
                'status' => 'sometimes|in:active,inactive',
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $updateData = $request->only(['full_name', 'email', 'phone', 'role', 'status']);
            $oldRole = $user->role;
            $newRole = $request->input('role');

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

            // Handle RBAC role assignment if role changed
            if ($newRole && $newRole !== $oldRole) {
                // Remove old RBAC roles if user had admin roles
                if (in_array($oldRole, ['admin', 'moderator', 'super_admin', 'support_agent', 'financial_manager', 'content_manager'])) {
                    try {
                        $oldRbacRole = \App\Models\Role::where('slug', $oldRole)->first();
                        if ($oldRbacRole) {
                            $this->adminRoleService->revokeRole($user->id, $oldRbacRole->id);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to revoke old RBAC role: ' . $e->getMessage());
                    }
                }

                // Assign new RBAC role if it's not 'buyer' or 'seller'
                if ($newRole !== 'buyer' && $newRole !== 'seller') {
                    try {
                        $rbacRole = \App\Models\Role::where('slug', $newRole)->where('is_active', true)->first();
                        if ($rbacRole) {
                            $this->adminRoleService->assignRole(
                                $user->id,
                                $rbacRole->id,
                                $request->user()?->id
                            );
                            Log::info("RBAC role '{$newRole}' assigned to user {$user->id} (updated from '{$oldRole}')");
                        } else {
                            Log::warning("RBAC role '{$newRole}' not found. Please run the seeder first.");
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to assign RBAC role to user: ' . $e->getMessage(), [
                            'user_id' => $user->id,
                            'role' => $newRole,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

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
            $hasOrders = $user->orders()->exists();
            // Check if user has store orders through their store
            if ($user->store) {
                $hasOrders = $hasOrders || $user->store->orders()->exists();
            }
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

    /**
     * Add user address (admin can add addresses for users)
     */
    public function addUserAddress(Request $request, $userId)
    {
        try {
            $request->validate([
                'label' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'line1' => 'required|string|max:255',
                'line2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'state' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255|default:NG',
                'zipcode' => 'nullable|string|max:20',
                'is_default' => 'nullable|boolean',
            ]);

            $user = User::findOrFail($userId);

            $data = $request->only([
                'label', 'phone', 'line1', 'line2', 'city', 'state', 'country', 'zipcode', 'is_default'
            ]);
            $data['user_id'] = $user->id;

            // Set city to null if not provided
            if (!isset($data['city']) || empty($data['city'])) {
                $data['city'] = null;
            }

            // If setting as default, unset other defaults
            if (!empty($data['is_default']) && $data['is_default']) {
                \App\Models\UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
            }

            $address = \App\Models\UserAddress::create($data);

            return ResponseHelper::success($address, 'Address added successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
