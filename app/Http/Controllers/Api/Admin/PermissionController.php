<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class PermissionController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * List all permissions (grouped by module)
     */
    public function index(Request $request)
    {
        try {
            $module = $request->get('module');
            $permissions = $this->permissionService->getPermissionsByModule($module);
            
            return ResponseHelper::success($permissions, 'Permissions retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get permission details
     */
    public function show($id)
    {
        try {
            $permission = $this->permissionService->getAllPermissions()->find($id);
            
            if (!$permission) {
                return ResponseHelper::error('Permission not found', 404);
            }
            
            return ResponseHelper::success($permission, 'Permission retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create new permission
     */
    public function store(PermissionRequest $request)
    {
        try {
            $permission = $this->permissionService->createPermission($request->validated());
            return ResponseHelper::success($permission, 'Permission created successfully', 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update permission
     */
    public function update(PermissionRequest $request, $id)
    {
        try {
            $permission = $this->permissionService->updatePermission($id, $request->validated());
            return ResponseHelper::success($permission, 'Permission updated successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete permission
     */
    public function destroy($id)
    {
        try {
            $this->permissionService->deletePermission($id);
            return ResponseHelper::success(null, 'Permission deleted successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get permissions by module
     */
    public function getByModule($module)
    {
        try {
            $permissions = $this->permissionService->getPermissionsByModule($module);
            return ResponseHelper::success($permissions, 'Permissions retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get static module list (read-only)
     */
    public function getModules()
    {
        try {
            $modules = $this->permissionService->getModules();
            return ResponseHelper::success($modules, 'Modules retrieved successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

