<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceStat;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminServicesController extends Controller
{
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
                      ->orWhere('short_description', 'like', "%{$search}%")
                      ->orWhereHas('store', function ($storeQuery) use ($search) {
                          $storeQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $services = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_services' => Service::count(),
                'active_services' => Service::where('status', 'active')->count(),
                'inactive_services' => Service::where('status', 'inactive')->count(),
                'sold_services' => Service::where('is_sold', true)->count(),
                'unavailable_services' => Service::where('is_unavailable', true)->count(),
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
            ]);

            $service = Service::findOrFail($serviceId);
            
            $service->update([
                'status' => $request->status,
                'is_sold' => $request->get('is_sold', $service->is_sold),
                'is_unavailable' => $request->get('is_unavailable', $service->is_unavailable),
            ]);

            return ResponseHelper::success([
                'service_id' => $service->id,
                'status' => $service->status,
                'is_sold' => $service->is_sold,
                'is_unavailable' => $service->is_unavailable,
                'updated_at' => $service->updated_at,
            ], 'Service status updated successfully');
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
                'name' => 'required|string|max:255',
                'short_description' => 'required|string|max:500',
                'full_description' => 'required|string',
                'price_from' => 'required|numeric|min:0',
                'price_to' => 'required|numeric|min:0|gte:price_from',
                'discount_price' => 'nullable|numeric|min:0',
                'status' => 'required|in:active,inactive',
            ]);

            $service = Service::findOrFail($serviceId);
            
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
