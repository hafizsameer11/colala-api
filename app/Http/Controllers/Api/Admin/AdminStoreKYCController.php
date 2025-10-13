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
            $query = Store::with(['user', 'businessDetails', 'addresses', 'deliveryPricing']);

            // Apply filters
            if ($request->has('kyc_status') && $request->kyc_status !== 'all') {
                switch ($request->kyc_status) {
                    case 'pending':
                        $query->where('kyc_status', 'pending');
                        break;
                    case 'approved':
                        $query->where('kyc_status', 'approved');
                        break;
                    case 'rejected':
                        $query->where('kyc_status', 'rejected');
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
                'pending_kyc' => Store::where('kyc_status', 'pending')->count(),
                'approved_kyc' => Store::where('kyc_status', 'approved')->count(),
                'rejected_kyc' => Store::where('kyc_status', 'rejected')->count(),
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
            $store = Store::with([
                'user',
                'businessDetails',
                'addresses',
                'deliveryPricing',
                'categories',
                'socialLinks',
                'onboardingSteps'
            ])->findOrFail($storeId);

            $storeData = [
                'store_info' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                    'email' => $store->email,
                    'phone' => $store->phone,
                    'location' => $store->store_location,
                    'kyc_status' => $store->kyc_status,
                    'onboarding_level' => $store->onboarding_level,
                    'is_active' => $store->is_active,
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
                            'name' => $category->name,
                        ];
                    }),
                    'social_links' => $store->socialLinks->map(function ($link) {
                        return [
                            'id' => $link->id,
                            'platform' => $link->platform,
                            'url' => $link->url,
                        ];
                    }),
                ],
                'level_2_data' => $store->businessDetails ? [
                    'business_name' => $store->businessDetails->business_name,
                    'business_type' => $store->businessDetails->business_type,
                    'business_registration_number' => $store->businessDetails->business_registration_number,
                    'tax_identification_number' => $store->businessDetails->tax_identification_number,
                    'business_address' => $store->businessDetails->business_address,
                    'business_phone' => $store->businessDetails->business_phone,
                    'business_email' => $store->businessDetails->business_email,
                    'documents' => $store->businessDetails->documents ?? [],
                ] : null,
                'level_3_data' => [
                    'physical_store_address' => $store->physical_store_address,
                    'store_phone' => $store->store_phone,
                    'store_hours' => $store->store_hours,
                    'utility_bill' => $store->utility_bill,
                    'addresses' => $store->addresses->map(function ($address) {
                        return [
                            'id' => $address->id,
                            'name' => $address->name,
                            'address' => $address->address,
                            'phone' => $address->phone,
                            'is_default' => $address->is_default,
                        ];
                    }),
                    'delivery_pricing' => $store->deliveryPricing->map(function ($pricing) {
                        return [
                            'id' => $pricing->id,
                            'name' => $pricing->name,
                            'price' => $pricing->price,
                            'estimated_days' => $pricing->estimated_days,
                            'is_default' => $pricing->is_default,
                        ];
                    }),
                    'theme_color' => $store->theme_color,
                ],
                'onboarding_progress' => $store->onboardingSteps ? [
                    'level_1_completed' => $store->onboardingSteps->level_1_completed ?? false,
                    'level_2_completed' => $store->onboardingSteps->level_2_completed ?? false,
                    'level_3_completed' => $store->onboardingSteps->level_3_completed ?? false,
                    'submitted_for_review' => $store->onboardingSteps->submitted_for_review ?? false,
                ] : null,
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
                'kyc_status' => 'required|in:pending,approved,rejected',
                'notes' => 'nullable|string|max:500',
                'send_notification' => 'boolean',
            ]);

            $store = Store::with('user')->findOrFail($storeId);
            $oldStatus = $store->kyc_status;
            
            $store->update([
                'kyc_status' => $request->kyc_status,
            ]);

            // Send notification to user if requested
            if ($request->get('send_notification', true)) {
                $this->sendKYCStatusNotification($store->user, $request->kyc_status, $request->notes);
            }

            return ResponseHelper::success([
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'old_status' => $oldStatus,
                'new_status' => $request->kyc_status,
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
                'action' => 'required|in:update_kyc_status,update_level,activate,deactivate',
                'store_ids' => 'required|array|min:1',
                'store_ids.*' => 'integer|exists:stores,id',
                'kyc_status' => 'required_if:action,update_kyc_status|in:pending,approved,rejected',
                'onboarding_level' => 'required_if:action,update_level|integer|min:1|max:3',
            ]);

            $storeIds = $request->store_ids;
            $action = $request->action;

            switch ($action) {
                case 'update_kyc_status':
                    Store::whereIn('id', $storeIds)->update(['kyc_status' => $request->kyc_status]);
                    return ResponseHelper::success(null, "Stores KYC status updated to {$request->kyc_status}");
                
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
     * Get KYC statistics
     */
    public function getKYCStatistics()
    {
        try {
            $stats = [
                'total_stores' => Store::count(),
                'pending_kyc' => Store::where('kyc_status', 'pending')->count(),
                'approved_kyc' => Store::where('kyc_status', 'approved')->count(),
                'rejected_kyc' => Store::where('kyc_status', 'rejected')->count(),
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
                SUM(CASE WHEN kyc_status = "approved" THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN kyc_status = "pending" THEN 1 ELSE 0 END) as pending_count
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
                'kyc_status' => $store->kyc_status,
                'onboarding_level' => $store->onboarding_level,
                'is_active' => $store->is_active,
                'created_at' => $store->created_at,
                'formatted_date' => $store->created_at->format('d-m-Y H:i A'),
                'status_color' => $this->getKYCStatusColor($store->kyc_status),
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
