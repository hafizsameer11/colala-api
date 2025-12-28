<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellerUserController extends Controller
{
    /**
     * Get all seller users with summary stats
     */
    public function index(Request $request)
    {
        try {
            $query = User::with(['store', 'wallet'])
                ->where('role', 'seller'); // Only sellers

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('store', function ($storeQuery) use ($search) {
                            $storeQuery->where('store_name', 'like', "%{$search}%");
                        });
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('is_active', $request->status === 'active');
            }

            // Level filter (store onboarding level)
            if ($request->has('level') && $request->level !== 'all') {
                $query->whereHas('store', function ($storeQuery) use ($request) {
                    $storeQuery->where('onboarding_level', $request->level);
                });
            }

            $users = $query->latest()->paginate(15);

            // Get summary stats (only for sellers)
            $totalStores = User::where('role', 'seller')->count();
            $activeStores = User::where('role', 'seller')->where('is_active', true)->count();
            $newStores = User::where('role', 'seller')->where('created_at', '>=', now()->subMonth())->count();

            $users->getCollection()->transform(function ($user) {
                $primaryStore = $user->store;
                return [
                    'id' => $user->id,
                    'store_id' => $primaryStore ? $primaryStore->id : null,
                    'store_name' => $primaryStore ? $primaryStore->store_name : 'No Store',
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'full_name' => $user->full_name,
                    'level' => $primaryStore ? $primaryStore->onboarding_level : 1,
                    'is_active' => $user->is_active,
                    'profile_picture' => $primaryStore && $primaryStore->profile_image ? asset('storage/' . $primaryStore->profile_image) : null,
                    'store_count' => $user->store ? 1 : 0,
                    'total_orders' => $this->getUserOrderCount($user->id),
                    'total_revenue' => $this->getUserRevenue($user->id),
                    'created_at' => $user->created_at ? $user->created_at->format('d-m-Y H:i:s') : null,
                    'last_login' => $user->updated_at ? $user->updated_at->format('d-m-Y H:i:s') : null
                ];
            });

            $summaryStats = [
                'total_stores' => [
                    'count' => $totalStores,
                    'increase' => 4, // Mock data
                    'color' => 'red'
                ],
                'active_stores' => [
                    'count' => $activeStores,
                    'increase' => 4, // Mock data
                    'color' => 'red'
                ],
                'new_stores' => [
                    'count' => $newStores,
                    'increase' => 4, // Mock data
                    'color' => 'red'
                ]
            ];

            return ResponseHelper::success([
                'users' => $users,
                'summary_stats' => $summaryStats
            ], 'Seller users retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller user statistics
     */
    public function stats()
    {
        try {
            $totalStores = User::where('role', 'seller')->count();
            $activeStores = User::where('role', 'seller')->where('is_active', true)->count();
            $newStores = User::where('role', 'seller')->where('created_at', '>=', now()->subMonth())->count();

            // Calculate percentage increase (mock data for now)
            $totalIncrease = 4;
            $activeIncrease = 4;
            $newIncrease = 4;

            return ResponseHelper::success([
                'total_stores' => [
                    'count' => $totalStores,
                    'increase' => $totalIncrease,
                    'color' => 'red'
                ],
                'active_stores' => [
                    'count' => $activeStores,
                    'increase' => $activeIncrease,
                    'color' => 'red'
                ],
                'new_stores' => [
                    'count' => $newStores,
                    'increase' => $newIncrease,
                    'color' => 'red'
                ]
            ], 'Seller user statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Search seller users
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'search' => 'required|string|min:2'
            ]);

            $search = $request->search;
            $users = User::where('role', 'seller')->with(['store', 'wallet'])
                ->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('store', function ($storeQuery) use ($search) {
                            $storeQuery->where('store_name', 'like', "%{$search}%");
                        });
                })
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    $primaryStore = $user->store;
                    return [
                        'id' => $user->id,
                        'store_id' => $primaryStore ? $primaryStore->id : null,
                        'store_name' => $primaryStore ? $primaryStore->store_name : 'No Store',
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'level' => $primaryStore ? $primaryStore->onboarding_level : 1,
                        'is_active' => $user->is_active,
                        'profile_picture' => $primaryStore && $primaryStore->profile_image ? asset('storage/' . $primaryStore->profile_image) : null
                    ];
                });

            return ResponseHelper::success($users, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on seller users
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'user_ids' => 'required|array',
                'action' => 'required|string|in:activate,deactivate,delete,export_csv,export_pdf,set_level'
            ]);

            $userIds = $request->user_ids;
            $action = $request->action;

            if ($action === 'activate') {
                User::where('role', 'seller')->whereIn('id', $userIds)->update(['is_active' => true]);
                $message = "Sellers activated successfully";
            } elseif ($action === 'deactivate') {
                User::where('role', 'seller')->whereIn('id', $userIds)->update(['is_active' => false]);
                $message = "Sellers deactivated successfully";
            } elseif ($action === 'set_level') {
                $request->validate(['level' => 'required|integer|min:1|max:3']);
                User::where('role', 'seller')->whereIn('id', $userIds)->update(['level' => $request->level]);
                $message = "Seller levels updated successfully";
            } elseif ($action === 'export_csv') {
                // CSV export logic would go here
                $message = "CSV export initiated";
            } elseif ($action === 'export_pdf') {
                // PDF export logic would go here
                $message = "PDF export initiated";
            } else {
                User::where('role', 'seller')->whereIn('id', $userIds)->delete();
                $message = "Sellers deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller user details
     */
    public function sellerDetails($id)
    {
        try {
            $user = User::where('role', 'seller')->with([
                'store',
                'wallet',
                'store.products',
                'store.orders'
            ])->findOrFail($id);

            $primaryStore = $user->store;

            $sellerDetails = [
                'user_info' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'level' => $user->level ?? 1,
                    'is_active' => $user->is_active,
                    'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                    'user_code' => $user->user_code,
                    'created_at' => $user->created_at->format('d-m-Y H:i:s'),
                    'last_login' => $user->updated_at->format('d-m-Y H:i:s')
                ],
                'store_info' => $primaryStore ? [
                    'id' => $primaryStore->id,
                    'store_name' => $primaryStore->store_name,
                    'store_email' => $primaryStore->store_email,
                    'store_phone' => $primaryStore->store_phone,
                    'status' => $primaryStore->status,
                    'profile_image' => $primaryStore->profile_image ? asset('storage/' . $primaryStore->profile_image) : null,
                    'business_type' => $primaryStore->business_type,
                    'description' => $primaryStore->description,
                    'address' => $primaryStore->address,
                    'city' => $primaryStore->city,
                    'state' => $primaryStore->state,
                    'country' => $primaryStore->country
                ] : null,
                'business_metrics' => [
                    'total_stores' => $user->store ? 1 : 0,
                    'total_products' => $user->store ? $user->store->products->count() : 0,
                    'total_orders' => $this->getUserOrderCount($user->id),
                    'total_revenue' => $this->getUserRevenue($user->id),
                    'wallet_balance' => $user->wallet ? [
                        'shopping_balance' => 'N' . number_format($user->wallet->shopping_balance, 2),
                        'reward_balance' => 'N' . number_format($user->wallet->reward_balance, 2),
                        'referral_balance' => 'N' . number_format($user->wallet->referral_balance, 2),
                        'loyalty_points' => $user->wallet->loyality_points
                    ] : null
                ],
                'store' => $user->store ? [
                    'id' => $user->store->id,
                    'store_name' => $user->store->store_name,
                    'status' => $user->store->status,
                    'products_count' => $user->store->products->count(),
                    'orders_count' => $user->store->orders->count(),
                    'created_at' => $user->store->created_at->format('d-m-Y H:i:s')
                ] : null
            ];

            return ResponseHelper::success($sellerDetails, 'Seller details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get seller transactions
     */
    public function sellerTransactions($id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);

            $transactions = Transaction::where('user_id', $id)
                ->with(['order'])
                ->latest()
                ->paginate(15);

            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tx_id' => $transaction->tx_id,
                    'amount' => 'N' . number_format($transaction->amount, 0),
                    'type' => ucfirst($transaction->type),
                    'status' => ucfirst($transaction->status),
                    'status_color' => $this->getTransactionStatusColor($transaction->status),
                    'payment_method' => 'Unknown', // Transaction model doesn't have payment_method field
                    'reference' => $transaction->tx_id, // Transaction model doesn't have reference field
                    'order' => $transaction->order ? [
                        'id' => $transaction->order->id,
                        'order_no' => $transaction->order->order_no
                    ] : null,
                    'created_at' => $transaction->created_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($transactions, 'Seller transactions retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Block/Unblock seller user
     */
    public function toggleBlock(Request $request, $id)
    {
        try {
            $request->validate([
                'action' => 'required|string|in:block,unblock'
            ]);

            $user = User::where('role', 'seller')->findOrFail($id);

            if ($request->action === 'block') {
                $user->update(['is_active' => false]);
                $message = "Seller blocked successfully";
            } else {
                $user->update(['is_active' => true]);
                $message = "Seller unblocked successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Remove seller user
     */
    public function removeSeller($id)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($id);
            // $user->delete();
            $user->update(['is_active' => false]);
            //now find store and update visibility to 0
            $store = Store::where('user_id', $id)->first();
            $store->update(['visibility' => 0]);
            //now find all products and update visibility to 0
            $products = Product::where('store_id', $store->id)->get();
            foreach ($products as $product) {
                $product->update(['visibility' => 0]);
            }
            //now find all services and update visibility to 0
            $services = Service::where('store_id', $store->id)->get();
            foreach ($services as $service) {
                $service->update(['visibility' => 0]);
            }
          
            //now find all reviews and update visibility to 0
        
            return ResponseHelper::success(null, 'Seller removed successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get user order count
     */
    private function getUserOrderCount($userId)
    {
        return Order::whereHas('storeOrders.store', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();
    }

    /**
     * Get user revenue
     */
    private function getUserRevenue($userId)
    {
        return Order::whereHas('storeOrders.store', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->sum('grand_total');
    }

    /**
     * Get transaction status color
     */
    private function getTransactionStatusColor($status)
    {
        $colors = [
            'successful' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'cancelled' => 'gray'
        ];

        return $colors[$status] ?? 'gray';
    }
}
