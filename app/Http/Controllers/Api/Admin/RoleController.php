<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * List all roles with permissions
     */
    public function index()
    {
        try {
            $roles = $this->roleService->getAllRoles();
            return ResponseHelper::success($roles, 'Roles retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get role details
     */
    public function show($id)
    {
        try {
            $role = $this->roleService->getRoleWithPermissions($id);
            return ResponseHelper::success($role, 'Role retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create new role
     */
    public function store(RoleRequest $request)
    {
        try {
            $role = $this->roleService->createRole($request->validated());
            return ResponseHelper::success($role, 'Role created successfully', 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update role
     */
    public function update(RoleRequest $request, $id)
    {
        try {
            $role = $this->roleService->updateRole($id, $request->validated());
            return ResponseHelper::success($role, 'Role updated successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete role
     */
    public function destroy($id)
    {
        try {
            $this->roleService->deleteRole($id);
            return ResponseHelper::success(null, 'Role deleted successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissions(Request $request, $id)
    {
        try {
            $request->validate([
                'permission_ids' => 'required|array',
                'permission_ids.*' => 'integer|exists:permissions,id',
            ]);

            $role = $this->roleService->assignPermissions($id, $request->permission_ids);
            return ResponseHelper::success($role, 'Permissions assigned successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get permissions for role
     */
    public function getPermissions($id)
    {
        try {
            $role = $this->roleService->getRoleWithPermissions($id);
            return ResponseHelper::success($role->permissions, 'Role permissions retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

