<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminStoreKYCController extends Controller
{
    /**
     * Get all stores with KYC status and filtering
     */
    public function getAllStores(Request $request)
    {
        try {
            $query = Store::withoutGlobalScopes()
                ->with(['user', 'businessDetails', 'addresses', 'deliveryPricing', 'accountOfficer']);

            // Account Officer sees only assigned stores
            if (auth()->user()->hasRole('account_officer') && 
                !auth()->user()->hasPermission('sellers.assign_account_officer')) {
                $query->where('account_officer_id', auth()->id());
            }

            // Super Admin can filter by account_officer_id
            if ($request->has('account_officer_id') && 
                auth()->user()->hasPermission('sellers.assign_account_officer')) {
                $query->where('account_officer_id', $request->account_officer_id);
            }

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                switch ($request->status) {
                    case 'pending':
                        $query->where('status', 'pending');
                        break;
                    case 'approved':
                        $query->where('status', 'approved');
                        break;
                    case 'rejected':
                        $query->where('status', 'rejected');
                        break;
                }
            }

            if ($request->has('level') && $request->level !== 'all') {
                $query->where('onboarding_level', $request->level);
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
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $stores = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_stores' => Store::count(),
                'pending_kyc' => Store::where('status', 'pending')->count(),
                'approved_kyc' => Store::where('status', 'approved')->count(),
                'rejected_kyc' => Store::where('status', 'rejected')->count(),
                'level_1_stores' => Store::where('onboarding_level', 1)->count(),
                'level_2_stores' => Store::where('onboarding_level', 2)->count(),
                'level_3_stores' => Store::where('onboarding_level', 3)->count(),
            ];

            return ResponseHelper::success([
                'stores' => $this->formatStoresData($stores),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $stores->currentPage(),
                    'last_page' => $stores->lastPage(),
                    'per_page' => $stores->perPage(),
                    'total' => $stores->total(),
                    'next_page_url' => $stores->nextPageUrl(),
                    'prev_page_url' => $stores->previousPageUrl(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed store information with all levels
     */
    public function getStoreDetails($storeId)
    {
        try {
            $store = Store::withoutGlobalScopes()
                ->with([
                    'user',
                    'businessDetails',
                    'addresses',
                    'deliveryPricing',
                    'categories',
                    'socialLinks',
                    'accountOfficer',
                ])->findOrFail($storeId);

            // Account Officer can only view assigned stores
            if (auth()->user()->hasRole('account_officer') && 
                !auth()->user()->hasPermission('sellers.assign_account_officer')) {
                if ($store->account_officer_id !== auth()->id()) {
                    return ResponseHelper::error('Unauthorized. You can only view stores assigned to you.', 403);
                }
            }

            $storeData = [
                'store_info' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                    'email' => $store->store_email,
                    'phone' => $store->store_phone,
                    'location' => $store->store_location,
                    'status' => $store->status,
                    'onboarding_level' => $store->onboarding_level ?? 0,
                    'account_officer' => $store->accountOfficer ? [
                        'id' => $store->accountOfficer->id,
                        'name' => $store->accountOfficer->full_name ?? $store->accountOfficer->name,
                        'email' => $store->accountOfficer->email,
                    ] : null,
                    'created_at' => $store->created_at,
                    'updated_at' => $store->updated_at,
                ],
                'owner_info' => [
                    'user_id' => $store->user->id,
                    'name' => $store->user->full_name,
                    'email' => $store->user->email,
                    'phone' => $store->user->phone,
                    'role' => $store->user->role,
                ],
                'level_1_data' => [
                    'profile_image' => $store->user->profile_picture,
                    'banner_image' => $store->banner_image,
                    'categories' => $store->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->title,
                        ];
                    }),
                    'social_links' => $store->socialLinks->map(function ($link) {
                        return [
                            'id' => $link->id,
                            'type' => $link->type,
                            'url' => $link->url,
                        ];
                    }),
                ],
                'level_2_data' => $store->businessDetails ? [
                    'business_name' => $store->businessDetails->registered_name,
                    'business_type' => $store->businessDetails->business_type,
                    'nin_number' => $store->businessDetails->nin_number,
                    'bn_number' => $store->businessDetails->bn_number,
                    'cac_number' => $store->businessDetails->cac_number,
                    'nin_document' => $store->businessDetails->nin_document,
                    'cac_document' => $store->businessDetails->cac_document,
                    'utility_bill' => $store->businessDetails->utility_bill,
                    'store_video' => $store->businessDetails->store_video,
                    'has_physical_store' => $store->businessDetails->has_physical_store,
                ] : null,
                'level_3_data' => [
                    'store_phone' => $store->store_phone,
                    'store_location' => $store->store_location,
                    'addresses' => $store->addresses->map(function ($address) {
                        return [
                            'id' => $address->id,
                            'full_address' => $address->full_address,
                            'state' => $address->state,
                            'local_government' => $address->local_government,
                            'is_main' => $address->is_main,
                        ];
                    }),
                    'delivery_pricing' => $store->deliveryPricing->map(function ($pricing) {
                        return [
                            'id' => $pricing->id,
                            'state' => $pricing->state,
                            'price' => $pricing->price,
                            'local_government' => $pricing->local_government,
                            'variant' => $pricing->variant,
                            'is_free' => $pricing->is_free,
                        ];
                    }),
                    'theme_color' => $store->theme_color,
                ],
                'onboarding_progress' => [
                    'onboarding_level' => $store->onboarding_level ?? 0,
                    'status' => $store->status,
                ],
            ];

            return ResponseHelper::success($storeData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update store KYC status and send notification
     */
    public function updateStoreStatus(Request $request, $storeId)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,approved,rejected',
                'notes' => 'nullable|string|max:500',
                'send_notification' => 'boolean',
            ]);

            $store = Store::with('user')->findOrFail($storeId);
            $oldStatus = $store->status;
            
            $store->update([
                'status' => $request->status,
            ]);

            // Send notification to user if requested
            if ($request->get('send_notification', true)) {
                $this->sendKYCStatusNotification($store->user, $request->status, $request->notes);
            }

            return ResponseHelper::success([
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'notification_sent' => $request->get('send_notification', true),
                'updated_at' => $store->updated_at,
            ], 'Store KYC status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update store onboarding level
     */
    public function updateStoreLevel(Request $request, $storeId)
    {
        try {
            $request->validate([
                'onboarding_level' => 'required|integer|min:1|max:3',
                'notes' => 'nullable|string|max:500',
            ]);

            $store = Store::with('user')->findOrFail($storeId);
            $oldLevel = $store->onboarding_level;
            
            $store->update([
                'onboarding_level' => $request->onboarding_level,
            ]);

            // Send notification about level update
            $this->sendLevelUpdateNotification($store->user, $request->onboarding_level, $request->notes);

            return ResponseHelper::success([
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'old_level' => $oldLevel,
                'new_level' => $request->onboarding_level,
                'updated_at' => $store->updated_at,
            ], 'Store onboarding level updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on stores
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|in:update_status,update_level,activate,deactivate',
                'store_ids' => 'required|array|min:1',
                'store_ids.*' => 'integer|exists:stores,id',
                'status' => 'required_if:action,update_status|in:pending,approved,rejected',
                'onboarding_level' => 'required_if:action,update_level|integer|min:1|max:3',
            ]);

            $storeIds = $request->store_ids;
            $action = $request->action;

            switch ($action) {
                case 'update_status':
                    Store::whereIn('id', $storeIds)->update(['status' => $request->status]);
                    return ResponseHelper::success(null, "Stores KYC status updated to {$request->status}");
                
                case 'update_level':
                    Store::whereIn('id', $storeIds)->update(['onboarding_level' => $request->onboarding_level]);
                    return ResponseHelper::success(null, "Stores onboarding level updated to {$request->onboarding_level}");
                
                case 'activate':
                    Store::whereIn('id', $storeIds)->update(['is_active' => true]);
                    return ResponseHelper::success(null, 'Stores activated successfully');
                
                case 'deactivate':
                    Store::whereIn('id', $storeIds)->update(['is_active' => false]);
                    return ResponseHelper::success(null, 'Stores deactivated successfully');
            }
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Assign or unassign account officer to a store
     * PUT /api/admin/stores/{id}/assign-account-officer
     * 
     * Access: Super Admin only
     */
    public function assignAccountOfficer(Request $request, $storeId)
    {
        try {
            // Only Super Admin can assign account officers
            if (!auth()->user()->hasPermission('sellers.assign_account_officer')) {
                return ResponseHelper::error('Unauthorized. Only Super Admins can assign account officers.', 403);
            }

            $store = Store::withoutGlobalScopes()->findOrFail($storeId);
            
            $request->validate([
                'account_officer_id' => 'nullable|exists:users,id'
            ]);

            // Verify user has account_officer role if provided
            if ($request->account_officer_id) {
                $user = User::findOrFail($request->account_officer_id);
                if (!$user->hasRole('account_officer')) {
                    return ResponseHelper::error('User must have Account Officer role', 422);
                }
            }

            $store->account_officer_id = $request->account_officer_id;
            $store->save();

            // Load relationship for response
            $store->load('accountOfficer');

            return ResponseHelper::success([
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'account_officer_id' => $store->account_officer_id,
                'account_officer' => $store->accountOfficer ? [
                    'id' => $store->accountOfficer->id,
                    'name' => $store->accountOfficer->full_name ?? $store->accountOfficer->name,
                    'email' => $store->accountOfficer->email,
                ] : null,
            ], $request->account_officer_id 
                ? 'Account Officer assigned successfully' 
                : 'Account Officer unassigned successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get KYC statistics
     */
    public function getKYCStatistics()
    {
        try {
            $stats = [
                'total_stores' => Store::count(),
                'pending_kyc' => Store::where('status', 'pending')->count(),
                'approved_kyc' => Store::where('status', 'approved')->count(),
                'rejected_kyc' => Store::where('status', 'rejected')->count(),
                'level_1_stores' => Store::where('onboarding_level', 1)->count(),
                'level_2_stores' => Store::where('onboarding_level', 2)->count(),
                'level_3_stores' => Store::where('onboarding_level', 3)->count(),
                'active_stores' => Store::where('is_active', true)->count(),
                'inactive_stores' => Store::where('is_active', false)->count(),
            ];

            // Monthly KYC submissions
            $monthlyStats = Store::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total_stores,
                SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count
            ')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            return ResponseHelper::success([
                'current_stats' => $stats,
                'monthly_trends' => $monthlyStats,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send KYC status notification to user
     */
    private function sendKYCStatusNotification($user, $status, $notes = null)
    {
        $statusMessages = [
            'approved' => 'Congratulations! Your store KYC has been approved.',
            'rejected' => 'Your store KYC application has been rejected.',
            'pending' => 'Your store KYC application is under review.',
        ];

        $title = 'Store KYC Status Update';
        $content = $statusMessages[$status] ?? 'Your store KYC status has been updated.';
        
        if ($notes) {
            $content .= "\n\nAdmin Notes: {$notes}";
        }

        UserNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'content' => $content,
            'is_read' => false,
        ]);
    }

    /**
     * Send level update notification to user
     */
    private function sendLevelUpdateNotification($user, $level, $notes = null)
    {
        $title = 'Store Onboarding Level Update';
        $content = "Your store onboarding level has been updated to Level {$level}.";
        
        if ($notes) {
            $content .= "\n\nAdmin Notes: {$notes}";
        }

        UserNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'content' => $content,
            'is_read' => false,
        ]);
    }

    /**
     * Format stores data for response
     */
    private function formatStoresData($stores)
    {
        return $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->store_name,
                'email' => $store->email,
                'phone' => $store->phone,
                'owner_name' => $store->user->full_name,
                'owner_email' => $store->user->email,
                'status' => $store->status,
                'onboarding_level' => $store->onboarding_level,
                'created_at' => $store->created_at,
                'formatted_date' => $store->created_at->format('d-m-Y H:i A'),
                'status_color' => $this->getKYCStatusColor($store->status),
            ];
        });
    }

    /**
     * Get KYC status color for UI
     */
    private function getKYCStatusColor($status)
    {
        return match($status) {
            'approved' => 'green',
            'pending' => 'yellow',
            'rejected' => 'red',
            default => 'blue'
        };
    }
}
