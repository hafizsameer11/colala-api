<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\SystemBanner;
use App\Models\SystemBannerView;
use App\Models\SystemBannerClick;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminBannerController extends Controller
{
    /**
     * Get all system banners
     */
    public function getAllBanners(Request $request)
    {
        try {
            $query = SystemBanner::with(['creator', 'bannerViews', 'bannerClicks'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by audience type
            if ($request->has('audience_type')) {
                $query->where('audience_type', $request->audience_type);
            }

            // Filter by position
            if ($request->has('position')) {
                $query->where('position', $request->position);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            }

            $banners = $query->paginate($request->get('per_page', 20));

            // Get statistics
            $stats = [
                'total_banners' => SystemBanner::count(),
                'active_banners' => SystemBanner::active()->count(),
                'total_views' => SystemBannerView::count(),
                'total_clicks' => SystemBannerClick::count(),
                'average_ctr' => SystemBannerView::count() > 0 
                    ? round((SystemBannerClick::count() / SystemBannerView::count()) * 100, 2)
                    : 0,
            ];

            return ResponseHelper::success([
                'banners' => $this->formatBannersData($banners),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $banners->currentPage(),
                    'last_page' => $banners->lastPage(),
                    'per_page' => $banners->perPage(),
                    'total' => $banners->total(),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create a new system banner
     */
    public function createBanner(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'image' => 'required|file|mimes:jpg,jpeg,png,gif|max:10240', // 10MB max
                'link' => 'nullable',
                'audience_type' => 'required|in:all,buyers,sellers,specific',
                'target_user_ids' => 'required_if:audience_type,specific|array',
                'target_user_ids.*' => 'exists:users,id',
                'position' => 'required|in:top,middle,bottom',
                'is_active' => 'boolean',
                'start_date' => 'nullable|date|after_or_equal:now',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error('Validation failed: ' . $validator->errors()->first(), 422);
            }

            DB::beginTransaction();

            // Handle image upload
            $imagePath = $request->file('image')->store('banners', 'public');

            // Create banner
            $banner = SystemBanner::create([
                'title' => $request->title,
                'image' => $imagePath,
                'link' => $request->link,
                'audience_type' => $request->audience_type,
                'target_user_ids' => $request->target_user_ids,
                'position' => $request->position,
                'is_active' => $request->boolean('is_active', true),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_by' => $request->user()->id,
            ]);

            DB::commit();

            return ResponseHelper::success([
                'banner' => $this->formatBannerData($banner),
                'message' => 'Banner created successfully'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get banner details
     */
    public function getBannerDetails($id)
    {
        try {
            $banner = SystemBanner::with([
                'creator',
                'bannerViews.user',
                'bannerClicks.user'
            ])->findOrFail($id);

            return ResponseHelper::success([
                'banner' => $this->formatBannerData($banner),
                'views' => $banner->bannerViews->map(function ($view) {
                    return [
                        'id' => $view->id,
                        'user' => [
                            'id' => $view->user->id,
                            'name' => $view->user->full_name,
                            'email' => $view->user->email,
                        ],
                        'viewed_at' => $view->viewed_at,
                        'ip_address' => $view->ip_address,
                    ];
                }),
                'clicks' => $banner->bannerClicks->map(function ($click) {
                    return [
                        'id' => $click->id,
                        'user' => [
                            'id' => $click->user->id,
                            'name' => $click->user->full_name,
                            'email' => $click->user->email,
                        ],
                        'clicked_at' => $click->clicked_at,
                        'ip_address' => $click->ip_address,
                    ];
                }),
                'statistics' => [
                    'total_views' => $banner->total_views,
                    'total_clicks' => $banner->total_clicks,
                    'click_through_rate' => $banner->click_through_rate,
                    'is_currently_active' => $banner->isCurrentlyActive(),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update banner
     */
    public function updateBanner(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'image' => 'sometimes|required|file|mimes:jpg,jpeg,png,gif|max:10240',
                'link' => 'nullable|url',
                'audience_type' => 'sometimes|required|in:all,buyers,sellers,specific',
                'target_user_ids' => 'required_if:audience_type,specific|array',
                'target_user_ids.*' => 'exists:users,id',
                'position' => 'sometimes|required|in:top,middle,bottom',
                'is_active' => 'boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error('Validation failed: ' . $validator->errors()->first(), 422);
            }

            $banner = SystemBanner::findOrFail($id);

            DB::beginTransaction();

            $updateData = $request->only([
                'title', 'link', 'audience_type', 'target_user_ids', 
                'position', 'is_active', 'start_date', 'end_date'
            ]);

            // Handle image update
            if ($request->hasFile('image')) {
                // Delete old image
                if ($banner->image) {
                    Storage::disk('public')->delete($banner->image);
                }
                $updateData['image'] = $request->file('image')->store('banners', 'public');
            }

            $banner->update($updateData);

            DB::commit();

            return ResponseHelper::success([
                'banner' => $this->formatBannerData($banner),
                'message' => 'Banner updated successfully'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete banner
     */
    public function deleteBanner($id)
    {
        try {
            $banner = SystemBanner::findOrFail($id);
            
            // Delete image
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            
            $banner->delete();

            return ResponseHelper::success([], 'Banner deleted successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Toggle banner active status
     */
    public function toggleBannerStatus($id)
    {
        try {
            $banner = SystemBanner::findOrFail($id);
            $banner->update(['is_active' => !$banner->is_active]);

            return ResponseHelper::success([
                'banner' => $this->formatBannerData($banner),
                'message' => $banner->is_active ? 'Banner activated' : 'Banner deactivated'
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get active banners for frontend
     */
    public function getActiveBanners(Request $request)
    {
        try {
            $user = $request->user();
            $userRole = $user->role;

            $banners = SystemBanner::active()
                ->forAudience($userRole)
                ->orderBy('position')
                ->orderBy('created_at', 'desc')
                ->get();

            return ResponseHelper::success([
                'banners' => $banners->map(function ($banner) {
                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'image_url' => $banner->image_url,
                        'link' => $banner->link,
                        'position' => $banner->position,
                    ];
                })
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Track banner view
     */
    public function trackBannerView(Request $request, $id)
    {
        try {
            $banner = SystemBanner::active()->findOrFail($id);
            $user = $request->user();

            // Check if user already viewed this banner today
            $existingView = SystemBannerView::where('banner_id', $banner->id)
                ->where('user_id', $user->id)
                ->whereDate('viewed_at', today())
                ->first();

            if (!$existingView) {
                SystemBannerView::create([
                    'banner_id' => $banner->id,
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'viewed_at' => now(),
                ]);
            }

            return ResponseHelper::success([], 'Banner view tracked');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Track banner click
     */
    public function trackBannerClick(Request $request, $id)
    {
        try {
            $banner = SystemBanner::active()->findOrFail($id);
            $user = $request->user();

            SystemBannerClick::create([
                'banner_id' => $banner->id,
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'clicked_at' => now(),
            ]);

            return ResponseHelper::success([], 'Banner click tracked');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get banner analytics
     */
    public function getBannerAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Banner performance over time
            $bannerPerformance = SystemBanner::with(['bannerViews', 'bannerClicks'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->get()
                ->map(function ($banner) {
                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'total_views' => $banner->total_views,
                        'total_clicks' => $banner->total_clicks,
                        'click_through_rate' => $banner->click_through_rate,
                        'is_active' => $banner->is_active,
                    ];
                });

            // Daily views and clicks
            $dailyStats = SystemBannerView::selectRaw('
                DATE(viewed_at) as date,
                COUNT(*) as views,
                (SELECT COUNT(*) FROM system_banner_clicks WHERE DATE(clicked_at) = DATE(system_banner_views.viewed_at)) as clicks
            ')
            ->whereBetween('viewed_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            return ResponseHelper::success([
                'banner_performance' => $bannerPerformance,
                'daily_stats' => $dailyStats,
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
     * Format banners data
     */
    private function formatBannersData($banners)
    {
        return $banners->map(function ($banner) {
            return $this->formatBannerData($banner);
        });
    }

    /**
     * Format single banner data
     */
    private function formatBannerData($banner)
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'image_url' => $banner->image_url,
            'link' => $banner->link,
            'audience_type' => $banner->audience_type,
            'target_user_ids' => $banner->target_user_ids,
            'position' => $banner->position,
            'is_active' => $banner->is_active,
            'start_date' => $banner->start_date,
            'end_date' => $banner->end_date,
            'created_by' => [
                'id' => $banner->creator->id,
                'name' => $banner->creator->full_name,
                'email' => $banner->creator->email,
            ],
            'total_views' => $banner->total_views,
            'total_clicks' => $banner->total_clicks,
            'click_through_rate' => $banner->click_through_rate,
            'is_currently_active' => $banner->isCurrentlyActive(),
            'created_at' => $banner->created_at,
            'updated_at' => $banner->updated_at,
        ];
    }
}
