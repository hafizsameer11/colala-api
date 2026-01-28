<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class AccountOfficerPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Create Account Officer permissions (using firstOrCreate to avoid duplicates)
            $permissions = [
                [
                    'name' => 'View Account Officer Vendors',
                    'slug' => 'account_officer_vendors.view',
                    'module' => 'account_officer_vendors',
                    'description' => 'Access Account Officer Vendors page',
                ],
                [
                    'name' => 'View Assigned Vendor Details',
                    'slug' => 'account_officer_vendors.view_details',
                    'module' => 'account_officer_vendors',
                    'description' => 'View details of assigned vendors',
                ],
                [
                    'name' => 'Assign Account Officer',
                    'slug' => 'sellers.assign_account_officer',
                    'module' => 'sellers',
                    'description' => 'Assign account officer to store',
                ],
            ];

            $permissionIds = [];
            foreach ($permissions as $permissionData) {
                $permission = Permission::firstOrCreate(
                    ['slug' => $permissionData['slug']],
                    $permissionData
                );
                $permissionIds[$permissionData['slug']] = $permission->id;
                $this->command->info("Permission '{$permissionData['slug']}' created/verified");
            }

            // Create Account Officer role if it doesn't exist
            $accountOfficerRole = Role::firstOrCreate(
                ['slug' => 'account_officer'],
                [
                    'name' => 'Account Officer',
                    'description' => 'Manages assigned vendors',
                    'is_active' => true,
                ]
            );
            $this->command->info("Role 'account_officer' created/verified");

            // Get additional permissions that Account Officer should have
            $additionalPermissions = [
                'dashboard.view',
                'sellers.view',
                'sellers.view_details',
                'seller_orders.view',
                'seller_orders.view_details',
                'seller_transactions.view',
                'seller_transactions.view_details',
            ];

            $allPermissionIds = [];
            
            // Add Account Officer specific permissions
            foreach ($permissionIds as $slug => $id) {
                $allPermissionIds[] = $id;
            }

            // Add additional permissions (sellers, orders, transactions, dashboard)
            foreach ($additionalPermissions as $slug) {
                $permission = Permission::where('slug', $slug)->first();
                if ($permission) {
                    $allPermissionIds[] = $permission->id;
                } else {
                    $this->command->warn("Permission '{$slug}' not found. Skipping...");
                }
            }

            // Remove duplicates
            $allPermissionIds = array_unique($allPermissionIds);

            // Assign all permissions to Account Officer role
            $accountOfficerRole->permissions()->sync($allPermissionIds);
            $this->command->info("Assigned " . count($allPermissionIds) . " permissions to Account Officer role");

            DB::commit();
            $this->command->info('✅ Account Officer permissions and role created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Failed to seed Account Officer permissions: ' . $e->getMessage());
            throw $e;
        }
    }
}
