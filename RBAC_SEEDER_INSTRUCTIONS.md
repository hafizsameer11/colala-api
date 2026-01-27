# RBAC Seeder Instructions

## Overview

You need to run the `RolePermissionSeeder` to populate your database with:
- ✅ All 100+ permissions from the inventory document
- ✅ Default roles (Super Admin, Admin, Moderator, Support Agent, Financial Manager, Content Manager)
- ✅ Permission assignments to roles
- ✅ Migration of existing admin users to the new role system

---

## Step 1: Run Database Migrations

First, make sure all migrations are run:

```bash
php artisan migrate
```

This will create the following tables:
- `roles`
- `permissions`
- `role_permission` (pivot table)
- `admin_roles`

---

## Step 2: Run the Seeder

You have two options:

### Option A: Run Only the RBAC Seeder

```bash
php artisan db:seed --class=RolePermissionSeeder
```

### Option B: Run All Seeders (if RolePermissionSeeder is in DatabaseSeeder)

```bash
php artisan db:seed
```

---

## What the Seeder Does

1. **Creates All Permissions** (100+ permissions)
   - Dashboard permissions (view, export)
   - Buyers permissions (view, view_details, edit, delete, export)
   - Sellers permissions (view, view_details, edit, suspend, activate, delete, export)
   - Products permissions (view, view_details, edit, approve, reject, delete, boost, export)
   - Orders permissions (view, view_details, update_status, cancel, refund, export)
   - Transactions permissions (view, view_details, approve_payout, reject_payout, export, refund)
   - KYC permissions (view, view_details, approve, reject, request_changes)
   - Subscriptions permissions (view, view_details, create_plan, edit_plan, delete_plan, manage)
   - Promotions permissions (view, view_details, create, edit, approve, reject, delete)
   - Social Feed permissions (view, view_details, approve, reject, delete, pin)
   - And many more...

2. **Creates Default Roles**
   - **Super Admin** - Full system access (all permissions)
   - **Admin** - Full operational access except system settings
   - **Moderator** - Content moderation and support
   - **Support Agent** - Customer support focused
   - **Financial Manager** - Financial operations
   - **Content Manager** - Content and product management

3. **Assigns Permissions to Roles**
   - Each role gets appropriate permissions based on their hierarchy
   - Super Admin gets ALL permissions
   - Other roles get subset of permissions

4. **Migrates Existing Admin Users**
   - Users with `role = 'super_admin'` → assigned Super Admin role
   - Users with `role = 'admin'` → assigned Admin role
   - Users with `role = 'moderator'` → assigned Moderator role

---

## Verify the Seeder Ran Successfully

After running the seeder, you can verify:

### Check Roles

```bash
php artisan tinker
```

Then in tinker:
```php
\App\Models\Role::all();
```

You should see 6 roles.

### Check Permissions

```php
\App\Models\Permission::count();
```

You should see 100+ permissions.

### Check User Roles

```php
$user = \App\Models\User::where('role', 'admin')->first();
$user->roles; // Should show assigned roles
$user->getAllPermissions(); // Should show all permissions
```

---

## Troubleshooting

### If Seeder Fails

1. **Check if migrations ran:**
   ```bash
   php artisan migrate:status
   ```

2. **Check for duplicate permissions:**
   The seeder uses `firstOrCreate` so it's safe to run multiple times, but if you see errors about unique constraints, you may need to:
   ```bash
   php artisan migrate:fresh --seed
   ```
   ⚠️ **Warning:** This will drop all tables and re-run migrations. Only use in development!

3. **Check existing admin users:**
   Make sure you have users with `role = 'admin'`, `role = 'super_admin'`, or `role = 'moderator'` in your users table.

---

## Re-running the Seeder

The seeder is **idempotent** - it's safe to run multiple times. It uses `firstOrCreate` which means:
- If permission/role exists, it won't create a duplicate
- If permission/role doesn't exist, it will create it
- Existing admin users won't get duplicate role assignments

You can safely run:
```bash
php artisan db:seed --class=RolePermissionSeeder
```
multiple times without issues.

---

## Next Steps

After running the seeder:

1. ✅ Test the API endpoints:
   ```bash
   GET /api/admin/rbac/me/permissions
   ```

2. ✅ Verify frontend can fetch permissions

3. ✅ Start implementing permission checks in frontend UI

---

## Important Notes

- **Existing Admin Users**: Users with `role = 'admin'`, `'super_admin'`, or `'moderator'` in the `users` table will automatically get assigned the corresponding RBAC role
- **No Data Loss**: Running the seeder won't delete or modify existing data (except adding role assignments)
- **Production**: Make sure to backup your database before running seeders in production

---

**Last Updated:** January 27, 2026

