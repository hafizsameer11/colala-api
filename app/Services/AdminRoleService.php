<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\AdminRole;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class AdminRoleService
{
    /**
     * Assign a role to a user
     */
    public function assignRole(int $userId, int $roleId, ?int $assignedBy = null): AdminRole
    {
        try {
            // Check if user already has this role
            $existingRole = AdminRole::where('user_id', $userId)
                ->where('role_id', $roleId)
                ->first();

            if ($existingRole) {
                return $existingRole;
            }

            return AdminRole::create([
                'user_id' => $userId,
                'role_id' => $roleId,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to assign role to user: ' . $e->getMessage());
            throw new Exception('Failed to assign role: ' . $e->getMessage());
        }
    }

    /**
     * Revoke a role from a user
     */
    public function revokeRole(int $userId, int $roleId): bool
    {
        try {
            AdminRole::where('user_id', $userId)
                ->where('role_id', $roleId)
                ->delete();
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to revoke role from user: ' . $e->getMessage());
            throw new Exception('Failed to revoke role: ' . $e->getMessage());
        }
    }

    /**
     * Get all roles for a user
     */
    public function getUserRoles(int $userId)
    {
        $user = User::findOrFail($userId);
        return $user->roles()->with('permissions')->get();
    }

    /**
     * Get all permissions for a user
     */
    public function getUserPermissions(int $userId): array
    {
        $user = User::findOrFail($userId);
        return $user->getAllPermissions();
    }

    /**
     * Migrate existing admin users to role system
     */
    public function migrateExistingAdmins(): array
    {
        $results = [
            'super_admin' => 0,
            'admin' => 0,
            'moderator' => 0,
            'errors' => [],
        ];

        try {
            DB::beginTransaction();

            // Get role slugs
            $superAdminRole = Role::where('slug', 'super_admin')->first();
            $adminRole = Role::where('slug', 'admin')->first();
            $moderatorRole = Role::where('slug', 'moderator')->first();

            if (!$superAdminRole || !$adminRole || !$moderatorRole) {
                throw new Exception('Default roles not found. Please run the seeder first.');
            }

            // Migrate super_admin users
            $superAdmins = User::where('role', 'super_admin')->get();
            foreach ($superAdmins as $user) {
                try {
                    $this->assignRole($user->id, $superAdminRole->id);
                    $results['super_admin']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Failed to migrate super_admin user {$user->id}: " . $e->getMessage();
                }
            }

            // Migrate admin users
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $user) {
                try {
                    $this->assignRole($user->id, $adminRole->id);
                    $results['admin']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Failed to migrate admin user {$user->id}: " . $e->getMessage();
                }
            }

            // Migrate moderator users
            $moderators = User::where('role', 'moderator')->get();
            foreach ($moderators as $user) {
                try {
                    $this->assignRole($user->id, $moderatorRole->id);
                    $results['moderator']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Failed to migrate moderator user {$user->id}: " . $e->getMessage();
                }
            }

            DB::commit();
            return $results;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to migrate existing admins: ' . $e->getMessage());
            throw $e;
        }
    }
}

