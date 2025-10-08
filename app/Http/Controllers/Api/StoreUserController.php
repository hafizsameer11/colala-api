<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\StoreUserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StoreUserController extends Controller
{
    private $storeUserService;

    public function __construct(StoreUserService $storeUserService)
    {
        $this->storeUserService = $storeUserService;
    }

    /**
     * Get all users for a store
     */
    public function index(Request $request, $storeId)
    {
        try {
            $users = $this->storeUserService->getStoreUsers($storeId);
            return ResponseHelper::success($users, 'Store users retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Invite a new user to the store
     */
    public function invite(Request $request, $storeId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8',
                'role' => 'required|in:admin,manager,staff',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = $this->storeUserService->inviteUser($storeId, $request->all());
            return ResponseHelper::success($user, 'User invited successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update user role and permissions
     */
    public function update(Request $request, $storeId, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'role' => 'required|in:admin,manager,staff',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = $this->storeUserService->updateUser($storeId, $userId, $request->all());
            return ResponseHelper::success($user, 'User updated successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Remove user from store
     */
    public function remove(Request $request, $storeId, $userId)
    {
        try {
            $this->storeUserService->removeUser($storeId, $userId);
            return ResponseHelper::success(null, 'User removed from store successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get available roles
     */
    public function getRoles()
    {
        try {
            $roles = $this->storeUserService->getAvailableRoles();
            return ResponseHelper::success($roles, 'Available roles retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
