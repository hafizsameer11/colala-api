<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountOfficerController extends Controller
{
    /**
     * Get all account officers with vendor counts
     * GET /api/admin/account-officers
     * 
     * Access: All authenticated admins
     */
    public function index()
    {
        try {
            $accountOfficers = User::accountOfficers()
                ->withCount(['assignedVendors as vendor_count'])
                ->withCount(['assignedVendors as active_vendors' => function($q) {
                    $q->where('status', 'active');
                }])
                ->withCount(['assignedVendors as inactive_vendors' => function($q) {
                    $q->where('status', '!=', 'active');
                }])
                ->get()
                ->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->full_name ?? $user->name ?? $user->user_name ?? 'Unknown',
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'vendor_count' => $user->vendor_count ?? 0,
                        'active_vendors' => $user->active_vendors ?? 0,
                        'inactive_vendors' => $user->inactive_vendors ?? 0,
                    ];
                });

            return ResponseHelper::success($accountOfficers, 'Account officers retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get vendors assigned to a specific account officer
     * GET /api/admin/account-officers/{id}/vendors
     * 
     * Access: All authenticated admins
     */
    public function getVendors($id, Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $search = $request->get('search');

            $query = Store::withoutGlobalScopes()
                ->where('account_officer_id', $id)
                ->with(['accountOfficer', 'user']);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%")
                      ->orWhere('store_email', 'like', "%{$search}%");
                });
            }

            $stores = $query->latest()->paginate($perPage);

            $formattedStores = $stores->map(function($store) {
                return [
                    'id' => $store->id,
                    'store_name' => $store->store_name,
                    'store_email' => $store->store_email,
                    'store_phone' => $store->store_phone,
                    'status' => $store->status,
                    'seller_name' => $store->user?->full_name ?? $store->user?->name ?? 'Unknown',
                    'seller_email' => $store->user?->email,
                    'created_at' => $store->created_at,
                    'account_officer' => $store->accountOfficer ? [
                        'id' => $store->accountOfficer->id,
                        'name' => $store->accountOfficer->full_name ?? $store->accountOfficer->name,
                        'email' => $store->accountOfficer->email,
                    ] : null,
                ];
            });

            return ResponseHelper::success([
                'vendors' => $formattedStores,
                'pagination' => [
                    'current_page' => $stores->currentPage(),
                    'per_page' => $stores->perPage(),
                    'total' => $stores->total(),
                    'last_page' => $stores->lastPage(),
                ]
            ], 'Vendors retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get dashboard stats for current Account Officer
     * GET /api/admin/account-officers/me/dashboard
     * 
     * Access: Account Officer only
     */
    public function myDashboard()
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('account_officer')) {
                return ResponseHelper::error('Unauthorized. Only Account Officers can access this endpoint.', 403);
            }

            $userId = $user->id;
            $totalVendors = Store::withoutGlobalScopes()
                ->where('account_officer_id', $userId)
                ->count();
            $activeVendors = Store::withoutGlobalScopes()
                ->where('account_officer_id', $userId)
                ->where('status', 'active')
                ->count();
            $inactiveVendors = $totalVendors - $activeVendors;

            return ResponseHelper::success([
                'total_vendors' => $totalVendors,
                'active_vendors' => $activeVendors,
                'inactive_vendors' => $inactiveVendors,
            ], 'Dashboard stats retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get vendors assigned to current user (Account Officer)
     * GET /api/admin/vendors/assigned-to-me
     * 
     * Access: Account Officer only
     */
    public function myVendors(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('account_officer')) {
                return ResponseHelper::error('Unauthorized. Only Account Officers can access this endpoint.', 403);
            }

            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $search = $request->get('search');
            $period = $request->get('period');

            $query = Store::withoutGlobalScopes()
                ->where('account_officer_id', $user->id)
                ->with(['accountOfficer', 'user']);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%")
                      ->orWhere('store_email', 'like', "%{$search}%");
                });
            }

            // Apply period filter if provided
            if ($period) {
                $dateFilter = $this->getDateFilter($period);
                if ($dateFilter) {
                    $query->whereBetween('created_at', $dateFilter);
                }
            }

            $stores = $query->latest()->paginate($perPage);

            $formattedStores = $stores->map(function($store) {
                return [
                    'id' => $store->id,
                    'store_name' => $store->store_name,
                    'store_email' => $store->store_email,
                    'store_phone' => $store->store_phone,
                    'status' => $store->status,
                    'seller_name' => $store->user?->full_name ?? $store->user?->name ?? 'Unknown',
                    'seller_email' => $store->user?->email,
                    'created_at' => $store->created_at,
                ];
            });

            return ResponseHelper::success([
                'vendors' => $formattedStores,
                'pagination' => [
                    'current_page' => $stores->currentPage(),
                    'per_page' => $stores->perPage(),
                    'total' => $stores->total(),
                    'last_page' => $stores->lastPage(),
                ]
            ], 'Assigned vendors retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Helper method to get date range from period string
     */
    private function getDateFilter($period)
    {
        $now = now();
        
        switch ($period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'this_week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'this_month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()];
            case 'this_year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            default:
                return null;
        }
    }
}
