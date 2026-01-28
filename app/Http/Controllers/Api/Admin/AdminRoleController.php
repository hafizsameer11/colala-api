<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoleRequest;
use App\Services\AdminRoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminRoleController extends Controller
{
    protected $adminRoleService;

    public function __construct(AdminRoleService $adminRoleService)
    {
        $this->adminRoleService = $adminRoleService;
    }

    /**
     * Get roles for a user
     */
    public function getUserRoles($userId)
    {
        try {
            $roles = $this->adminRoleService->getUserRoles($userId);
            return ResponseHelper::success($roles, 'User roles retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(AssignRoleRequest $request, $userId)
    {
        try {
            $assignedBy = $request->user()->id;
            $adminRole = $this->adminRoleService->assignRole($userId, $request->role_id, $assignedBy);
            return ResponseHelper::success($adminRole, 'Role assigned successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Revoke role from user
     */
    public function revokeRole(Request $request, $userId, $roleId)
    {
        try {
            // Prevent users from removing their own admin access
            if ($userId == $request->user()->id) {
                return ResponseHelper::error('You cannot remove your own admin role', 403);
            }

            $this->adminRoleService->revokeRole($userId, $roleId);
            return ResponseHelper::success(null, 'Role revoked successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($userId)
    {
        try {
            $permissions = $this->adminRoleService->getUserPermissions($userId);
            return ResponseHelper::success($permissions, 'User permissions retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Check if user has a specific permission
     */
    public function checkPermission(Request $request, $userId)
    {
        try {
            $request->validate([
                'permission' => 'required|string',
            ]);

            $user = \App\Models\User::findOrFail($userId);
            $hasPermission = $user->hasPermission($request->permission);

            return ResponseHelper::success([
                'has_permission' => $hasPermission,
                'permission' => $request->permission,
            ], 'Permission check completed');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get current authenticated user's permissions and roles
     */
    public function getMyPermissions(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ResponseHelper::error('Unauthenticated', 401);
            }

            $roles = $this->adminRoleService->getUserRoles($user->id);
            $permissions = $this->adminRoleService->getUserPermissions($user->id);

            return ResponseHelper::success([
                'user_id' => $user->id,
                'roles' => $roles,
                'permissions' => $permissions,
            ], 'User permissions retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

