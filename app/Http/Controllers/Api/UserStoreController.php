<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\StoreUserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserStoreController extends Controller
{
    private $storeUserService;

    public function __construct(StoreUserService $storeUserService)
    {
        $this->storeUserService = $storeUserService;
    }

    /**
     * Get user's stores
     */
    public function index(Request $request)
    {
        try {
            $stores = $this->storeUserService->getUserStores($request->user()->id);
            return ResponseHelper::success($stores, 'User stores retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Join a store (for invited users)
     */
    public function join(Request $request, $storeId)
    {
        try {
            $result = $this->storeUserService->joinStore($storeId, $request->user()->id);
            return ResponseHelper::success($result, 'Successfully joined the store');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Check if user has permission for store
     */
    public function checkPermission(Request $request, $storeId)
    {
        try {
            $permission = $request->query('permission');
            if (!$permission) {
                return ResponseHelper::error('Permission parameter is required', 400);
            }

            $hasPermission = $this->storeUserService->hasStorePermission(
                $request->user()->id,
                $storeId,
                $permission
            );

            return ResponseHelper::success([
                'has_permission' => $hasPermission,
                'permission' => $permission,
                'store_id' => $storeId,
            ], 'Permission check completed');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
