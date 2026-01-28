<?php

namespace App\Helpers;

use App\Models\Permission;
use App\Models\User;

class PermissionHelper
{
    /**
     * Get all available permissions
     */
    public static function getAllPermissions()
    {
        return Permission::with('roles')->get();
    }

    /**
     * Get permissions grouped by module
     */
    public static function getPermissionsByModule(?string $module = null)
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
    public static function getModules(): array
    {
        $configPath = config_path('rbac_modules.php');
        
        if (file_exists($configPath)) {
            return require $configPath;
        }
        
        // Fallback to empty array if config doesn't exist
        return [];
    }

    /**
     * Check if user has permission
     */
    public static function checkUserPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    /**
     * Check if user has any of the permissions
     */
    public static function checkUserAnyPermission(User $user, array $permissions): bool
    {
        return $user->hasAnyPermission($permissions);
    }
}

