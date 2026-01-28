<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\StoreOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTracking;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductStat;
use App\Models\ProductEmbedding;
use App\Models\BoostProduct;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\ServiceStat;
use App\Models\SubService;
use App\Models\Escrow;
use App\Models\Dispute;
use App\Models\DisputeChat;
use App\Models\DisputeChatMessage;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\StoreReview;
use App\Models\ProductReview;
use App\Models\StoreOnboardingStep;
use App\Models\StoreAddress;
use App\Models\StoreDeliveryPricing;
use App\Models\StoreBusinessDetail;
use App\Models\StoreSocialLink;
use App\Models\StoreVisitor;
use App\Models\StoreFollow;
use App\Models\Announcement;
use App\Models\Banner;
use App\Models\AddOnService;
use App\Models\AddOnServiceChat;
use App\Models\Subscription;
use App\Models\StoreReferralEarning;
use App\Models\SavedItem;
use App\Models\BulkUploadJob;
use App\Models\StoreUser;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
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
     * Access: Admin users
     */
    public function assignAccountOfficer(Request $request, $storeId)
    {
        try {
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
     * Hard delete a store and all major related data.
     *
     * WARNING: This is a destructive operation intended for admin use only.
     * It attempts to remove the store and its main relationships (products, services,
     * orders, chats, disputes, onboarding, etc.) to minimize orphaned references.
     *
     * DELETE /api/admin/stores/{storeId}/hard-delete
     */
    public function hardDeleteStore(Request $request, $storeId)
    {
        try {
            DB::transaction(function () use ($storeId) {
                // Load store (ignoring visibility scope) with owner
                $store = Store::withoutGlobalScopes()->with('user')->findOrFail($storeId);
                $owner = $store->user;

                // -------- PRODUCTS & RELATED --------
                $productIds = Product::withoutGlobalScopes()
                    ->where('store_id', $store->id)
                    ->pluck('id')
                    ->all();

                if (!empty($productIds)) {
                    // Product-related tables
                    ProductImage::whereIn('product_id', $productIds)->delete();
                    ProductVariant::whereIn('product_id', $productIds)->delete();
                    ProductStat::whereIn('product_id', $productIds)->delete();
                    ProductEmbedding::whereIn('product_id', $productIds)->delete();
                    BoostProduct::whereIn('product_id', $productIds)->delete();

                    // Reviews & saved items
                    ProductReview::whereIn('product_id', $productIds)->delete();
                    SavedItem::whereIn('product_id', $productIds)->delete();

                    // Order items for these products
                    OrderItem::whereIn('product_id', $productIds)->delete();

                    Product::withoutGlobalScopes()
                        ->whereIn('id', $productIds)
                        ->delete();
                }

                // -------- SERVICES & RELATED --------
                $serviceIds = Service::where('store_id', $store->id)->pluck('id')->all();
                if (!empty($serviceIds)) {
                    ServiceMedia::whereIn('service_id', $serviceIds)->delete();
                    ServiceStat::whereIn('service_id', $serviceIds)->delete();
                    SubService::whereIn('service_id', $serviceIds)->delete();
                    Service::whereIn('id', $serviceIds)->delete();
                }

                // -------- STORE ORDERS, ORDERS, ESCROW, DISPUTES --------
                $storeOrderIds = StoreOrder::where('store_id', $store->id)->pluck('id')->all();
                $orderIds = [];
                if (!empty($storeOrderIds)) {
                    $orderIds = StoreOrder::whereIn('id', $storeOrderIds)
                        ->pluck('order_id')
                        ->filter()
                        ->unique()
                        ->all();

                    // Order tracking
                    OrderTracking::whereIn('store_order_id', $storeOrderIds)->delete();

                    // Escrow records
                    Escrow::whereIn('store_order_id', $storeOrderIds)->delete();
                    if (!empty($orderIds)) {
                        Escrow::whereIn('order_id', $orderIds)->delete();
                    }

                    // Disputes & dispute chats/messages
                    $disputes = Dispute::whereIn('store_order_id', $storeOrderIds)->get();
                    $disputeIds = $disputes->pluck('id')->all();
                    $disputeChatIds = DisputeChat::whereIn('dispute_id', $disputeIds)->pluck('id')->all();
                    if (!empty($disputeChatIds)) {
                        DisputeChatMessage::whereIn('dispute_chat_id', $disputeChatIds)->delete();
                        DisputeChat::whereIn('id', $disputeChatIds)->delete();
                    }
                    if (!empty($disputeIds)) {
                        Dispute::whereIn('id', $disputeIds)->delete();
                    }

                    // Finally delete store orders
                    StoreOrder::whereIn('id', $storeOrderIds)->delete();
                }

                if (!empty($orderIds)) {
                    // Delete orders that belonged only to this store (new flow is one store per order)
                    Order::whereIn('id', $orderIds)->delete();
                    // Delete transactions linked to those orders
                    Transaction::whereIn('order_id', $orderIds)->delete();
                }

                // -------- CHATS --------
                $chatIds = Chat::where('store_id', $store->id)->pluck('id')->all();
                if (!empty($chatIds)) {
                    ChatMessage::whereIn('chat_id', $chatIds)->delete();
                    Chat::whereIn('id', $chatIds)->delete();
                }

                // -------- REVIEWS --------
                StoreReview::where('store_id', $store->id)->delete();

                // -------- STORE-LEVEL ENTITIES --------
                StoreOnboardingStep::where('store_id', $store->id)->delete();
                StoreAddress::where('store_id', $store->id)->delete();
                StoreDeliveryPricing::where('store_id', $store->id)->delete();
                StoreBusinessDetail::where('store_id', $store->id)->delete();
                StoreSocialLink::where('store_id', $store->id)->delete();
                StoreVisitor::where('store_id', $store->id)->delete();
                StoreFollow::where('store_id', $store->id)->delete();
                Announcement::where('store_id', $store->id)->delete();
                Banner::where('store_id', $store->id)->delete();
                AddOnService::where('store_id', $store->id)->delete();
                AddOnServiceChat::where('store_id', $store->id)->delete();
                Subscription::where('store_id', $store->id)->delete();
                StoreReferralEarning::where('store_id', $store->id)->delete();
                BulkUploadJob::where('store_id', $store->id)->delete();
                StoreUser::where('store_id', $store->id)->delete();

                // Support tickets/messages scoped by store owner (optional, but helps avoid noise)
                if ($owner) {
                    $supportTicketIds = SupportTicket::where('user_id', $owner->id)->pluck('id')->all();
                    if (!empty($supportTicketIds)) {
                        SupportMessage::whereIn('ticket_id', $supportTicketIds)->delete();
                        SupportTicket::whereIn('id', $supportTicketIds)->delete();
                    }
                }

                // Finally, delete the store itself
                $store->delete();
                // NOTE: We are not deleting the owner user or wallet; that data may be needed
                // for audit/history elsewhere. If you want to remove the user as well, that
                // should be a separate explicit operation.
            });

            return ResponseHelper::success(null, 'Store and related data hard-deleted successfully');
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
