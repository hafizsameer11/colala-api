<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Exception;

class RoleService
{
    /**
     * Create a new role
     */
    public function createRole(array $data): Role
    {
        try {
            return Role::create($data);
        } catch (Exception $e) {
            Log::error('Failed to create role: ' . $e->getMessage());
            throw new Exception('Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Update a role
     */
    public function updateRole(int $id, array $data): Role
    {
        try {
            $role = Role::findOrFail($id);
            $role->update($data);
            return $role->fresh();
        } catch (Exception $e) {
            Log::error('Failed to update role: ' . $e->getMessage());
            throw new Exception('Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * Delete a role
     */
    public function deleteRole(int $id): bool
    {
        try {
            $role = Role::findOrFail($id);
            $role->delete();
            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete role: ' . $e->getMessage());
            throw new Exception('Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissions(int $roleId, array $permissionIds): Role
    {
        try {
            $role = Role::findOrFail($roleId);
            $role->permissions()->sync($permissionIds);
            return $role->load('permissions');
        } catch (Exception $e) {
            Log::error('Failed to assign permissions to role: ' . $e->getMessage());
            throw new Exception('Failed to assign permissions: ' . $e->getMessage());
        }
    }

    /**
     * Get role with permissions
     */
    public function getRoleWithPermissions(int $id): Role
    {
        return Role::with('permissions')->findOrFail($id);
    }

    /**
     * Get all roles with permissions
     */
    public function getAllRoles()
    {
        return Role::with('permissions')->get();
    }
}

