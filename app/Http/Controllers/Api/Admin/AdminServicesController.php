<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceStat;
use App\Models\Store;
use App\Models\User;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminServicesController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get all services with filtering and pagination
     */
    public function getAllServices(Request $request)
    {
        try {
            $query = Service::with(['store.user', 'media', 'serviceCategory', 'stats']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                switch ($request->status) {
                    case 'active':
                        $query->where('status', 'active');
                        break;
                    case 'inactive':
                        $query->where('status', 'inactive');
                        break;
                    case 'sold':
                        $query->where('is_sold', true);
                        break;
                    case 'unavailable':
                        $query->where('is_unavailable', true);
                        break;
                }
            }

            if ($request->has('category') && $request->category !== 'all') {
                $query->where('service_category_id', $request->category);
            }

            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
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
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('short_description', 'like', "%{$search}%")
                      ->orWhereHas('store', function ($storeQuery) use ($search) {
                          $storeQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $services = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics with period filtering
            $totalServicesQuery = Service::query();
            $activeServicesQuery = Service::where('status', 'active');
            $inactiveServicesQuery = Service::where('status', 'inactive');
            $soldServicesQuery = Service::where('is_sold', true);
            $unavailableServicesQuery = Service::where('is_unavailable', true);
            
            if ($period) {
                $this->applyPeriodFilter($totalServicesQuery, $period);
                $this->applyPeriodFilter($activeServicesQuery, $period);
                $this->applyPeriodFilter($inactiveServicesQuery, $period);
                $this->applyPeriodFilter($soldServicesQuery, $period);
                $this->applyPeriodFilter($unavailableServicesQuery, $period);
            }
            
            $stats = [
                'total_services' => $totalServicesQuery->count(),
                'active_services' => $activeServicesQuery->count(),
                'inactive_services' => $inactiveServicesQuery->count(),
                'sold_services' => $soldServicesQuery->count(),
                'unavailable_services' => $unavailableServicesQuery->count(),
            ];

            return ResponseHelper::success([
                'services' => $this->formatServicesData($services),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $services->currentPage(),
                    'last_page' => $services->lastPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed service information
     */
    public function getServiceDetails($serviceId)
    {
        try {
            $service = Service::with([
                'store.user',
                'media',
                'serviceCategory',
                'stats',
                'subServices'
            ])->findOrFail($serviceId);

            $serviceData = [
                'service_info' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'short_description' => $service->short_description,
                    'full_description' => $service->full_description,
                    'price_from' => $service->price_from,
                    'price_to' => $service->price_to,
                    'discount_price' => $service->discount_price,
                    'status' => $service->status,
                    'is_sold' => $service->is_sold,
                    'is_unavailable' => $service->is_unavailable,
                    'video' => $service->video,
                    'created_at' => $service->created_at,
                    'updated_at' => $service->updated_at,
                ],
                'store_info' => [
                    'store_id' => $service->store->id,
                    'store_name' => $service->store->store_name,
                    'seller_name' => $service->store->user->full_name,
                    'seller_email' => $service->store->user->email,
                    'store_location' => $service->store->store_location,
                ],
                'category_info' => $service->serviceCategory ? [
                    'id' => $service->serviceCategory->id,
                    'title' => $service->serviceCategory->title,
                ] : null,
                'media' => $service->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'type' => $media->type,
                        'url' => asset('storage/' . $media->path),
                    ];
                }),
                'sub_services' => $service->subServices->map(function ($subService) {
                    return [
                        'id' => $subService->id,
                        'name' => $subService->name,
                        'price_from' => $subService->price_from,
                        'price_to' => $subService->price_to,
                    ];
                }),
                'statistics' => $this->getServiceStatistics($service),
            ];

            return ResponseHelper::success($serviceData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update service status
     */
    public function updateServiceStatus(Request $request, $serviceId)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive',
                'is_sold' => 'boolean',
                'is_unavailable' => 'boolean',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);

            $service = Service::with(['store.user'])->findOrFail($serviceId);
            
            $updateData = [
                'status' => $request->status,
                'is_sold' => $request->get('is_sold', $service->is_sold),
                'is_unavailable' => $request->get('is_unavailable', $service->is_unavailable),
            ];

            // If status is inactive and rejection_reason is provided, save it
            if ($request->status === 'inactive' && $request->has('rejection_reason')) {
                $updateData['rejection_reason'] = $request->rejection_reason;
            } elseif ($request->status === 'active') {
                // Clear rejection reason when service is activated
                $updateData['rejection_reason'] = null;
            }

            $service->update($updateData);

            // Send notification to seller if service is marked as inactive
            if ($request->status === 'inactive' && $service->store && $service->store->user) {
                $seller = $service->store->user;
                $rejectionReason = $request->rejection_reason ?? 'No reason provided';
                
                $title = 'Service Rejected';
                $message = "Your service '{$service->name}' has been marked as inactive.";
                
                if ($request->rejection_reason) {
                    $message .= "\n\nReason: {$rejectionReason}";
                }

                try {
                    UserNotificationHelper::notify(
                        $seller->id,
                        $title,
                        $message,
                        [
                            'type' => 'service_rejected',
                            'service_id' => $service->id,
                            'service_name' => $service->name,
                            'rejection_reason' => $rejectionReason,
                        ]
                    );

                    Log::info('Service rejection notification sent to seller', [
                        'service_id' => $service->id,
                        'seller_id' => $seller->id,
                        'seller_email' => $seller->email,
                        'has_expo_token' => !empty($seller->expo_push_token),
                        'rejection_reason' => $rejectionReason
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send service rejection notification', [
                        'service_id' => $service->id,
                        'seller_id' => $seller->id,
                        'seller_email' => $seller->email,
                        'has_expo_token' => !empty($seller->expo_push_token),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Don't fail the request if notification fails
                }
            }

            return ResponseHelper::success([
                'service_id' => $service->id,
                'status' => $service->status,
                'rejection_reason' => $service->rejection_reason,
                'is_sold' => $service->is_sold,
                'is_unavailable' => $service->is_unavailable,
                'updated_at' => $service->updated_at,
            ], 'Service status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Approve service (set status to active)
     */
    public function approveService(Request $request, $serviceId)
    {
        try {
            $service = Service::with(['store.user'])->findOrFail($serviceId);
            
            $service->update([
                'status' => 'active',
                'rejection_reason' => null, // Clear any previous rejection reason
            ]);

            // Send notification to seller when service is approved
            if ($service->store && $service->store->user) {
                $seller = $service->store->user;
                
                $title = 'Service Approved';
                $message = "Your service '{$service->name}' has been approved and is now active.";

                try {
                    UserNotificationHelper::notify(
                        $seller->id,
                        $title,
                        $message,
                        [
                            'type' => 'service_approved',
                            'service_id' => $service->id,
                            'service_name' => $service->name,
                        ]
                    );

                    Log::info('Service approval notification sent to seller', [
                        'service_id' => $service->id,
                        'seller_id' => $seller->id,
                        'seller_email' => $seller->email,
                        'has_expo_token' => !empty($seller->expo_push_token),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send service approval notification', [
                        'service_id' => $service->id,
                        'seller_id' => $seller->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the request if notification fails
                }
            }

            return ResponseHelper::success([
                'service_id' => $service->id,
                'status' => $service->status,
                'updated_at' => $service->updated_at,
            ], 'Service approved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update service information
     */
    public function updateService(Request $request, $serviceId)
    {
        try {
            $request->validate([
                'name' => 'nullable|string|max:255',
                'short_description' => 'nullable|string|max:500',
                'full_description' => 'nullable|string',
                'price_from' => 'nullable|numeric|min:0',
                'price_to' => 'nullable|numeric|min:0',
                'discount_price' => 'nullable|numeric|min:0',
                'status' => 'nullable|in:draft,active,inactive',
                'service_category_id' => 'nullable|exists:service_categories,id',
                'is_sold' => 'nullable|boolean',
                'is_unavailable' => 'nullable|boolean',
            ]);
            
            // Handle category_id - if provided, treat it as service_category_id
            if ($request->has('category_id') && !$request->has('service_category_id')) {
                $request->merge(['service_category_id' => $request->category_id]);
            }

            $service = Service::findOrFail($serviceId);
            
            // Build update data only with provided fields
            $updateData = [];
            
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('short_description')) {
                $updateData['short_description'] = $request->short_description;
            }
            if ($request->has('full_description')) {
                $updateData['full_description'] = $request->full_description;
            }
            if ($request->has('price_from')) {
                $updateData['price_from'] = $request->price_from;
            }
            if ($request->has('price_to')) {
                $updateData['price_to'] = $request->price_to;
                // Validate price_to >= price_from if both are provided
                if ($request->has('price_from') && $request->price_to < $request->price_from) {
                    return ResponseHelper::error('price_to must be greater than or equal to price_from', 422);
                }
            }
            if ($request->has('discount_price')) {
                $updateData['discount_price'] = $request->discount_price;
            }
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
                // Clear rejection reason when status is set to active
                if ($request->status === 'active') {
                    $updateData['rejection_reason'] = null;
                }
            }
            // Services use service_category_id, not category_id
            if ($request->has('service_category_id')) {
                $updateData['service_category_id'] = $request->service_category_id;
            }
            if ($request->has('is_sold')) {
                $updateData['is_sold'] = $request->is_sold;
            }
            if ($request->has('is_unavailable')) {
                $updateData['is_unavailable'] = $request->is_unavailable;
            }
            
            $service->update($updateData);

            return ResponseHelper::success([
                'service_id' => $service->id,
                'name' => $service->name,
                'short_description' => $service->short_description,
                'full_description' => $service->full_description,
                'price_from' => $service->price_from,
                'price_to' => $service->price_to,
                'discount_price' => $service->discount_price,
                'status' => $service->status,
                'category_id' => $service->category_id,
                'service_category_id' => $service->service_category_id,
                'updated_at' => $service->updated_at,
            ], 'Service updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete service
     */
    public function deleteService($serviceId)
    {
        try {
            $service = Service::findOrFail($serviceId);
            
            // Delete related data
            // $service->media()->delete();
            // $service->subServices()->delete();
            // $service->stats()->delete();
            // $service->delete();
            $service->visibility = 0;
            $service->save();

            return ResponseHelper::success(null, 'Service deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get service statistics
     */
    private function getServiceStatistics($service)
    {
        $stats = $service->statsSummary();
        
        return [
            'views' => $stats['view'],
            'impressions' => $stats['impression'],
            'clicks' => $stats['click'],
            'chats' => $stats['chat'],
            'phone_views' => $stats['phone_view'],
            'total_engagement' => array_sum($stats),
            'sub_services_count' => $service->subServices->count(),
            'media_count' => $service->media->count(),
        ];
    }

    /**
     * Get service analytics
     */
    public function getServiceAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Top performing services
            $topServices = Service::with(['store', 'media', 'serviceCategory'])
                ->selectRaw('
                    services.*,
                    (SELECT COUNT(*) FROM service_stats WHERE service_id = services.id AND event_type = "view") as views,
                    (SELECT COUNT(*) FROM service_stats WHERE service_id = services.id AND event_type = "click") as clicks,
                    (SELECT COUNT(*) FROM service_stats WHERE service_id = services.id AND event_type = "chat") as chats
                ')
                ->orderByDesc('views')
                ->limit(10)
                ->get();

            // Category breakdown
            $categoryStats = Service::selectRaw('
                service_categories.name as category_name,
                COUNT(*) as service_count,
                AVG(services.price_from) as avg_price_from,
                AVG(services.price_to) as avg_price_to
            ')
            ->join('service_categories', 'services.service_category_id', '=', 'service_categories.id')
            ->groupBy('service_categories.id', 'service_categories.name')
            ->get();

            return ResponseHelper::success([
                'top_services' => $topServices,
                'category_breakdown' => $categoryStats,
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
     * Get seller services (for seller-specific management)
     */
    public function getSellerServices($sellerId)
    {
        try {
            $seller = User::with('store')->findOrFail($sellerId);
            $store = $seller->store;

            if (!$store) {
                return ResponseHelper::error('Seller does not have a store', 404);
            }

            $services = Service::with(['media', 'serviceCategory', 'stats'])
                ->where('store_id', $store->id)
                ->latest()
                ->paginate(20);

            $stats = [
                'total_services' => Service::where('store_id', $store->id)->count(),
                'active_services' => Service::where('store_id', $store->id)->where('status', 'active')->count(),
                'inactive_services' => Service::where('store_id', $store->id)->where('status', 'inactive')->count(),
                'sold_services' => Service::where('store_id', $store->id)->where('is_sold', true)->count(),
                'unavailable_services' => Service::where('store_id', $store->id)->where('is_unavailable', true)->count(),
            ];

            return ResponseHelper::success([
                'services' => $this->formatServicesData($services),
                'statistics' => $stats,
                'seller_info' => [
                    'seller_id' => $seller->id,
                    'seller_name' => $seller->full_name,
                    'store_name' => $store->store_name,
                ],
                'pagination' => [
                    'current_page' => $services->currentPage(),
                    'last_page' => $services->lastPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update seller service
     */
    public function updateSellerService(Request $request, $sellerId, $serviceId)
    {
        try {
            $seller = User::with('store')->findOrFail($sellerId);
            $store = $seller->store;

            if (!$store) {
                return ResponseHelper::error('Seller does not have a store', 404);
            }

            $service = Service::where('store_id', $store->id)->findOrFail($serviceId);

            $request->validate([
                'name' => 'required|string|max:255',
                'short_description' => 'required|string|max:500',
                'full_description' => 'required|string',
                'price_from' => 'required|numeric|min:0',
                'price_to' => 'required|numeric|min:0|gte:price_from',
                'discount_price' => 'nullable|numeric|min:0',
                'status' => 'required|in:active,inactive',
            ]);

            $service->update([
                'name' => $request->name,
                'short_description' => $request->short_description,
                'full_description' => $request->full_description,
                'price_from' => $request->price_from,
                'price_to' => $request->price_to,
                'discount_price' => $request->discount_price,
                'status' => $request->status,
            ]);

            return ResponseHelper::success([
                'service_id' => $service->id,
                'name' => $service->name,
                'price_from' => $service->price_from,
                'price_to' => $service->price_to,
                'updated_at' => $service->updated_at,
            ], 'Seller service updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete seller service
     */
    public function deleteSellerService($sellerId, $serviceId)
    {
        try {
            $seller = User::with('store')->findOrFail($sellerId);
            $store = $seller->store;

            if (!$store) {
                return ResponseHelper::error('Seller does not have a store', 404);
            }

            $service = Service::where('store_id', $store->id)->findOrFail($serviceId);
            
            // Delete related data
            $service->media()->delete();
            $service->subServices()->delete();
            $service->stats()->delete();
            $service->delete();

            return ResponseHelper::success(null, 'Seller service deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format services data for response
     */
    private function formatServicesData($services)
    {
        return $services->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'short_description' => $service->short_description,
                'price_from' => $service->price_from,
                'price_to' => $service->price_to,
                'discount_price' => $service->discount_price,
                'store_name' => $service->store->store_name,
                'seller_name' => $service->store->user->full_name,
                'category' => $service->serviceCategory ? [
                    'id' => $service->serviceCategory->id,
                    'title' => $service->serviceCategory->title,
                    'image' => $service->serviceCategory->image ?? null,
                    'image_url' => $service->serviceCategory->image ? asset('storage/' . $service->serviceCategory->image) : null,
                    'is_active' => $service->serviceCategory->is_active ?? true,
                ] : null,
                'category_name' => $service->serviceCategory->title ?? null,
                'status' => $service->status,
                'is_sold' => $service->is_sold,
                'is_unavailable' => $service->is_unavailable,
                'sub_services_count' => $service->subServices->count(),
                'media_count' => $service->media->count(),
                'created_at' => $service->created_at,
                'formatted_date' => $service->created_at->format('d-m-Y H:i A'),
                'primary_media' => $service->media->first() ? 
                    asset('storage/' . $service->media->first()->path) : null,
            ];
        });
    }
}
