<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Exception;

class PermissionService
{
    /**
     * Create a new permission
     */
    public function createPermission(array $data): Permission
    {
        try {
            return Permission::create($data);
        } catch (Exception $e) {
            Log::error('Failed to create permission: ' . $e->getMessage());
            throw new Exception('Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * Update a permission
     */
    public function updatePermission(int $id, array $data): Permission
    {
        try {
            $permission = Permission::findOrFail($id);
            $permission->update($data);
            return $permission->fresh();
        } catch (Exception $e) {
            Log::error('Failed to update permission: ' . $e->getMessage());
            throw new Exception('Failed to update permission: ' . $e->getMessage());
        }
    }

    /**
     * Delete a permission
     */
    public function deletePermission(int $id): bool
    {
        try {
            $permission = Permission::findOrFail($id);
            $permission->delete();
            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete permission: ' . $e->getMessage());
            throw new Exception('Failed to delete permission: ' . $e->getMessage());
        }
    }

    /**
     * Get permissions grouped by module
     */
    public function getPermissionsByModule(?string $module = null)
    {
        $query = Permission::with('roles');
        
        if ($module) {
            $query->where('module', $module);
        }
        
        return $query->get()->groupBy('module');
    }

    /**
     * Get static module list from config
     */
    public function getModules(): array
    {
        $configPath = config_path('rbac_modules.php');
        
        if (file_exists($configPath)) {
            return require $configPath;
        }
        
        // Fallback to default modules if config doesn't exist yet
        return [];
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions()
    {
        return Permission::with('roles')->get();
    }
}

