<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementRequest;
use App\Http\Requests\BannerRequest;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\BannerResource;
use App\Models\Announcement;
use App\Models\Banner;
use App\Models\Store;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\BannerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SellerAnnouncementBannerController extends Controller
{
    private AnnouncementService $announcementService;
    private BannerService $bannerService;

    public function __construct(AnnouncementService $announcementService, BannerService $bannerService)
    {
        $this->announcementService = $announcementService;
        $this->bannerService = $bannerService;
    }

    /**
     * Get all announcements for a specific seller
     */
    public function getSellerAnnouncements(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $announcements = Announcement::where('store_id', $store->id)
                ->with('store')
                ->latest()
                ->paginate($request->get('per_page', 20));

            return ResponseHelper::success([
                'announcements' => AnnouncementResource::collection($announcements),
                'pagination' => [
                    'current_page' => $announcements->currentPage(),
                    'last_page' => $announcements->lastPage(),
                    'per_page' => $announcements->perPage(),
                    'total' => $announcements->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get all banners for a specific seller
     */
    public function getSellerBanners(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $banners = Banner::where('store_id', $store->id)
                ->with('store')
                ->latest()
                ->paginate($request->get('per_page', 20));

            return ResponseHelper::success([
                'banners' => BannerResource::collection($banners),
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
     * Get announcement details
     */
    public function getAnnouncementDetails($userId, $announcementId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $announcement = Announcement::where('id', $announcementId)
                ->where('store_id', $store->id)
                ->with('store')
                ->firstOrFail();

            return ResponseHelper::success([
                'announcement' => new AnnouncementResource($announcement),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                    'user' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get banner details
     */
    public function getBannerDetails($userId, $bannerId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $banner = Banner::where('id', $bannerId)
                ->where('store_id', $store->id)
                ->with('store')
                ->firstOrFail();

            return ResponseHelper::success([
                'banner' => new BannerResource($banner),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                    'user' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create announcement for a seller
     */
    public function createAnnouncement(AnnouncementRequest $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $announcement = $this->announcementService->create($store, $request->validated());

            return ResponseHelper::success([
                'announcement' => new AnnouncementResource($announcement),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Announcement created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create banner for a seller
     */
    public function createBanner(BannerRequest $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $banner = $this->bannerService->create($store, $request->validated());

            return ResponseHelper::success([
                'banner' => new BannerResource($banner),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Banner created successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update announcement
     */
    public function updateAnnouncement(AnnouncementRequest $request, $userId, $announcementId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $announcement = Announcement::where('id', $announcementId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $announcement->update([
                'message' => $request->message,
            ]);

            return ResponseHelper::success([
                'announcement' => new AnnouncementResource($announcement->fresh()),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Announcement updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update banner
     */
    public function updateBanner(BannerRequest $request, $userId, $bannerId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $banner = Banner::where('id', $bannerId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $data = [];
            
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                    Storage::disk('public')->delete($banner->image_path); // keep as model uses image_path
                }
                
                $path = $request->file('image')->store('banners', 'public');
                $data['image_path'] = $path;
            }
            
            if ($request->filled('link')) {
                $data['link'] = $request->link;
            }

            $banner->update($data);

            return ResponseHelper::success([
                'banner' => new BannerResource($banner->fresh()),
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ], 'Banner updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete announcement
     */
    public function deleteAnnouncement($userId, $announcementId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $announcement = Announcement::where('id', $announcementId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $announcement->delete();

            return ResponseHelper::success(null, 'Announcement deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete banner
     */
    public function deleteBanner($userId, $bannerId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $banner = Banner::where('id', $bannerId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            // Delete image file if exists
            if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                Storage::disk('public')->delete($banner->image_path);
            }

            $banner->delete();

            return ResponseHelper::success(null, 'Banner deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get statistics for seller announcements and banners
     */
    public function getStatistics($userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $announcementStats = Announcement::where('store_id', $store->id)
                ->selectRaw('
                    COUNT(*) as total_announcements,
                    SUM(impressions) as total_impressions,
                    AVG(impressions) as avg_impressions
                ')
                ->first();

            $bannerStats = Banner::where('store_id', $store->id)
                ->selectRaw('
                    COUNT(*) as total_banners,
                    SUM(impressions) as total_impressions,
                    AVG(impressions) as avg_impressions
                ')
                ->first();

            return ResponseHelper::success([
                'announcements' => [
                    'total' => $announcementStats->total_announcements ?? 0,
                    'total_impressions' => $announcementStats->total_impressions ?? 0,
                    'avg_impressions' => round($announcementStats->avg_impressions ?? 0, 2),
                ],
                'banners' => [
                    'total' => $bannerStats->total_banners ?? 0,
                    'total_impressions' => $bannerStats->total_impressions ?? 0,
                    'avg_impressions' => round($bannerStats->avg_impressions ?? 0, 2),
                ],
                'store' => [
                    'id' => $store->id,
                    'name' => $store->store_name,
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on announcements
     */
    public function bulkActionAnnouncements(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $request->validate([
                'action' => 'required|in:delete',
                'announcement_ids' => 'required|array|min:1',
                'announcement_ids.*' => 'integer|exists:announcements,id'
            ]);

            $announcementIds = $request->announcement_ids;
            $action = $request->action;

            // Verify all announcements belong to this store
            $announcements = Announcement::whereIn('id', $announcementIds)
                ->where('store_id', $store->id)
                ->get();

            if ($announcements->count() !== count($announcementIds)) {
                return ResponseHelper::error('Some announcements do not belong to this seller', 403);
            }

            switch ($action) {
                case 'delete':
                    Announcement::whereIn('id', $announcementIds)->delete();
                    return ResponseHelper::success(null, 'Announcements deleted successfully');
            }

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions on banners
     */
    public function bulkActionBanners(Request $request, $userId)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'seller')->firstOrFail();
            $store = $user->store;
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            $request->validate([
                'action' => 'required|in:delete',
                'banner_ids' => 'required|array|min:1',
                'banner_ids.*' => 'integer|exists:banners,id'
            ]);

            $bannerIds = $request->banner_ids;
            $action = $request->action;

            // Verify all banners belong to this store
            $banners = Banner::whereIn('id', $bannerIds)
                ->where('store_id', $store->id)
                ->get();

            if ($banners->count() !== count($bannerIds)) {
                return ResponseHelper::error('Some banners do not belong to this seller', 403);
            }

            switch ($action) {
                case 'delete':
                    // Delete image files
                    foreach ($banners as $banner) {
                        if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                            Storage::disk('public')->delete($banner->image_path);
                        }
                    }
                    
                    Banner::whereIn('id', $bannerIds)->delete();
                    return ResponseHelper::success(null, 'Banners deleted successfully');
            }

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
