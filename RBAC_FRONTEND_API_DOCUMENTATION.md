# RBAC Frontend API Documentation

## Overview

This document describes the Role-Based Access Control (RBAC) API endpoints available for the frontend to manage permissions and roles. The backend provides all the data needed for frontend to control UI visibility and access.

**Base URL:** `/api/admin/rbac`

**Authentication:** All endpoints require `auth:sanctum` middleware (Bearer token)

---

## Quick Start

### Get Current User's Permissions

The most important endpoint for frontend - get all permissions and roles for the currently authenticated user:

```http
GET /api/admin/rbac/me/permissions
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "user_id": 1,
    "roles": [
      {
        "id": 1,
        "name": "Super Admin",
        "slug": "super_admin",
        "description": "Full system access with all permissions",
        "is_active": true,
        "permissions": [
          {
            "id": 1,
            "name": "View Dashboard",
            "slug": "dashboard.view",
            "module": "dashboard"
          }
          // ... more permissions
        ]
      }
    ],
    "permissions": [
      "dashboard.view",
      "dashboard.export",
      "buyers.view",
      "buyers.view_details",
      // ... all permission slugs
    ]
  },
  "message": "User permissions retrieved successfully"
}
```

**Frontend Usage:**
```javascript
// Check if user has permission
const hasPermission = userPermissions.includes('dashboard.view');

// Check if user has any of multiple permissions
const canView = ['buyers.view', 'sellers.view'].some(perm => 
  userPermissions.includes(perm)
);
```

---

## API Endpoints

### 1. Get Current User's Permissions

Get roles and permissions for the authenticated user.

```http
GET /api/admin/rbac/me/permissions
```

**Response:** See Quick Start section above.

---

### 2. Get All Modules

Get the list of all available modules (static/organizational).

```http
GET /api/admin/rbac/modules
```

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "slug": "dashboard",
      "name": "Dashboard",
      "description": "Main analytics and overview dashboard",
      "icon": "dashboard",
      "order": 1
    },
    {
      "slug": "buyers",
      "name": "Buyers Management",
      "description": "Manage all buyer/customer accounts",
      "icon": "users",
      "order": 2
    }
    // ... more modules
  ],
  "message": "Modules retrieved successfully"
}
```

---

### 3. Get All Permissions

Get all permissions, optionally filtered by module.

```http
GET /api/admin/rbac/permissions
GET /api/admin/rbac/permissions?module=dashboard
```

**Query Parameters:**
- `module` (optional): Filter permissions by module slug

**Response:**
```json
{
  "status": "success",
  "data": {
    "dashboard": [
      {
        "id": 1,
        "name": "View Dashboard",
        "slug": "dashboard.view",
        "module": "dashboard",
        "description": "View dashboard statistics",
        "roles": []
      },
      {
        "id": 2,
        "name": "Export Dashboard Data",
        "slug": "dashboard.export",
        "module": "dashboard",
        "description": "Export dashboard data",
        "roles": []
      }
    ],
    "buyers": [
      // ... permissions for buyers module
    ]
  },
  "message": "Permissions retrieved successfully"
}
```

---

### 4. Get Permissions by Module

Get all permissions for a specific module.

```http
GET /api/admin/rbac/permissions/module/{module}
```

**Example:**
```http
GET /api/admin/rbac/permissions/module/dashboard
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "dashboard": [
      {
        "id": 1,
        "name": "View Dashboard",
        "slug": "dashboard.view",
        "module": "dashboard",
        "description": "View dashboard statistics"
      }
    ]
  },
  "message": "Permissions retrieved successfully"
}
```

---

### 5. Get Permission Details

Get details of a specific permission.

```http
GET /api/admin/rbac/permissions/{id}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "View Dashboard",
    "slug": "dashboard.view",
    "module": "dashboard",
    "description": "View dashboard statistics",
    "roles": [
      {
        "id": 1,
        "name": "Super Admin",
        "slug": "super_admin"
      }
    ]
  },
  "message": "Permission retrieved successfully"
}
```

---

### 6. Get All Roles

Get all available roles with their permissions.

```http
GET /api/admin/rbac/roles
```

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Super Admin",
      "slug": "super_admin",
      "description": "Full system access with all permissions",
      "is_active": true,
      "permissions": [
        {
          "id": 1,
          "name": "View Dashboard",
          "slug": "dashboard.view",
          "module": "dashboard"
        }
        // ... more permissions
      ]
    },
    {
      "id": 2,
      "name": "Admin",
      "slug": "admin",
      "description": "Full operational access except system settings",
      "is_active": true,
      "permissions": []
    }
    // ... more roles
  ],
  "message": "Roles retrieved successfully"
}
```

---

### 7. Get Role Details

Get details of a specific role with its permissions.

```http
GET /api/admin/rbac/roles/{id}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Super Admin",
    "slug": "super_admin",
    "description": "Full system access with all permissions",
    "is_active": true,
    "permissions": [
      {
        "id": 1,
        "name": "View Dashboard",
        "slug": "dashboard.view",
        "module": "dashboard"
      }
      // ... all permissions for this role
    ]
  },
  "message": "Role retrieved successfully"
}
```

---

### 8. Get User's Roles

Get all roles assigned to a specific user.

```http
GET /api/admin/rbac/users/{userId}/roles
```

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Super Admin",
      "slug": "super_admin",
      "description": "Full system access with all permissions",
      "is_active": true,
      "pivot": {
        "user_id": 1,
        "role_id": 1,
        "assigned_by": null,
        "assigned_at": "2026-01-27T17:00:00.000000Z"
      },
      "permissions": []
    }
  ],
  "message": "User roles retrieved successfully"
}
```

---

### 9. Get User's Permissions

Get all permissions for a specific user (from all their roles).

```http
GET /api/admin/rbac/users/{userId}/permissions
```

**Response:**
```json
{
  "status": "success",
  "data": [
    "dashboard.view",
    "dashboard.export",
    "buyers.view",
    "buyers.view_details",
    "buyers.edit",
    // ... all permission slugs
  ],
  "message": "User permissions retrieved successfully"
}
```

---

### 10. Check User Permission

Check if a specific user has a specific permission.

```http
POST /api/admin/rbac/users/{userId}/check-permission
```

**Request Body:**
```json
{
  "permission": "dashboard.view"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "has_permission": true,
    "permission": "dashboard.view"
  },
  "message": "Permission check completed"
}
```

---

## Permission Structure

### Permission Format

Permissions follow the pattern: `{module}.{action}`

**Examples:**
- `dashboard.view` - View dashboard
- `buyers.view` - View buyers list
- `buyers.edit` - Edit buyer information
- `products.approve` - Approve products
- `settings.admin_management` - Manage admins

### Available Actions

- `view` - View/list items
- `view_details` - View detailed information
- `create` - Create new items
- `edit` - Edit existing items
- `delete` - Delete items
- `approve` - Approve items
- `reject` - Reject items
- `export` - Export data
- `manage` - Full management access

---

## Modules List

| Module Slug | Module Name | Description |
|------------|-------------|-------------|
| `dashboard` | Dashboard | Main analytics and overview dashboard |
| `buyers` | Buyers Management | Manage all buyer/customer accounts |
| `sellers` | Sellers Management | Manage all seller stores/shops |
| `products` | Products & Services | Manage all products and services |
| `orders` | Orders Management | Manage all orders (buyers and sellers) |
| `transactions` | Transactions | View and manage all financial transactions |
| `kyc` | Store KYC | Verify and manage seller KYC documents |
| `subscriptions` | Subscriptions | Manage seller subscription plans |
| `promotions` | Promotions | Manage seller promotions, coupons, and discounts |
| `social_feed` | Social Feed | Manage seller social media posts |
| `all_users` | All Users | Unified view of all users |
| `balance` | Balance Management | View and manage user wallet balances |
| `chats` | Chats | Monitor and manage user chats |
| `analytics` | Analytics | Advanced analytics and reporting |
| `leaderboard` | Leaderboard | View and manage user leaderboards |
| `support` | Support | Manage customer support tickets |
| `disputes` | Disputes | Manage order and transaction disputes |
| `withdrawals` | Withdrawal Requests | Approve or reject withdrawal requests |
| `ratings` | Ratings & Reviews | Manage product and store ratings/reviews |
| `referrals` | Referral Management | Manage referral program settings |
| `notifications` | Notifications | Create and manage system notifications |
| `seller_help` | Seller Help Requests | Manage help requests from sellers |
| `settings` | Settings | System configuration and admin management |

---

## Frontend Implementation Examples

### React/TypeScript Example

```typescript
// hooks/usePermissions.ts
import { useState, useEffect } from 'react';
import axios from 'axios';

interface Permission {
  id: number;
  name: string;
  slug: string;
  module: string;
}

interface UserPermissions {
  user_id: number;
  roles: Array<{
    id: number;
    name: string;
    slug: string;
    permissions: Permission[];
  }>;
  permissions: string[];
}

export const usePermissions = () => {
  const [permissions, setPermissions] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPermissions = async () => {
      try {
        const response = await axios.get<UserPermissions>(
          '/api/admin/rbac/me/permissions',
          {
            headers: {
              Authorization: `Bearer ${localStorage.getItem('token')}`
            }
          }
        );
        setPermissions(response.data.data.permissions);
      } catch (error) {
        console.error('Failed to fetch permissions:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchPermissions();
  }, []);

  const hasPermission = (permission: string): boolean => {
    return permissions.includes(permission);
  };

  const hasAnyPermission = (permissionList: string[]): boolean => {
    return permissionList.some(perm => permissions.includes(perm));
  };

  const hasAllPermissions = (permissionList: string[]): boolean => {
    return permissionList.every(perm => permissions.includes(perm));
  };

  return {
    permissions,
    loading,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions
  };
};
```

### Usage in Components

```tsx
// components/Dashboard.tsx
import { usePermissions } from '../hooks/usePermissions';

const Dashboard = () => {
  const { hasPermission, hasAnyPermission } = usePermissions();

  return (
    <div>
      {hasPermission('dashboard.view') && (
        <DashboardStats />
      )}
      
      {hasAnyPermission(['dashboard.export', 'analytics.export']) && (
        <ExportButton />
      )}
      
      {hasPermission('buyers.view') && (
        <Link to="/buyers">View Buyers</Link>
      )}
    </div>
  );
};
```

### Vue.js Example

```javascript
// composables/usePermissions.js
import { ref, onMounted } from 'vue';
import axios from 'axios';

export const usePermissions = () => {
  const permissions = ref([]);
  const loading = ref(true);

  const fetchPermissions = async () => {
    try {
      const response = await axios.get('/api/admin/rbac/me/permissions', {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('token')}`
        }
      });
      permissions.value = response.data.data.permissions;
    } catch (error) {
      console.error('Failed to fetch permissions:', error);
    } finally {
      loading.value = false;
    }
  };

  const hasPermission = (permission) => {
    return permissions.value.includes(permission);
  };

  const hasAnyPermission = (permissionList) => {
    return permissionList.some(perm => permissions.value.includes(perm));
  };

  onMounted(() => {
    fetchPermissions();
  });

  return {
    permissions,
    loading,
    hasPermission,
    hasAnyPermission
  };
};
```

### Usage in Vue Component

```vue
<template>
  <div>
    <DashboardStats v-if="hasPermission('dashboard.view')" />
    <ExportButton v-if="hasAnyPermission(['dashboard.export', 'analytics.export'])" />
    <router-link v-if="hasPermission('buyers.view')" to="/buyers">
      View Buyers
    </router-link>
  </div>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions';

const { hasPermission, hasAnyPermission } = usePermissions();
</script>
```

---

## Common Permission Checks

### Navigation Menu

```javascript
// Show/hide menu items based on permissions
const menuItems = [
  {
    label: 'Dashboard',
    route: '/dashboard',
    permission: 'dashboard.view'
  },
  {
    label: 'Buyers',
    route: '/buyers',
    permission: 'buyers.view'
  },
  {
    label: 'Sellers',
    route: '/sellers',
    permission: 'sellers.view'
  },
  {
    label: 'Products',
    route: '/products',
    permission: 'products.view'
  }
];

// Filter menu items
const visibleMenuItems = menuItems.filter(item => 
  hasPermission(item.permission)
);
```

### Action Buttons

```javascript
// Show edit button only if user has edit permission
{hasPermission('buyers.edit') && (
  <button onClick={handleEdit}>Edit</button>
)}

// Show delete button only if user has delete permission
{hasPermission('buyers.delete') && (
  <button onClick={handleDelete}>Delete</button>
)}
```

### Route Protection

```javascript
// React Router example
const ProtectedRoute = ({ permission, children }) => {
  const { hasPermission } = usePermissions();
  
  if (!hasPermission(permission)) {
    return <Navigate to="/unauthorized" />;
  }
  
  return children;
};

// Usage
<Route 
  path="/buyers" 
  element={
    <ProtectedRoute permission="buyers.view">
      <BuyersPage />
    </ProtectedRoute>
  } 
/>
```

---

## Error Responses

All endpoints return errors in the following format:

```json
{
  "status": "error",
  "message": "Error message here"
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `401` - Unauthenticated (missing or invalid token)
- `403` - Forbidden (insufficient permissions - though not enforced in backend currently)
- `404` - Resource not found
- `422` - Validation error
- `500` - Server error

---

## Best Practices

1. **Cache Permissions**: Fetch user permissions once on login/app initialization and cache them
2. **Check Before Rendering**: Always check permissions before rendering UI elements
3. **Graceful Degradation**: If permission check fails, hide the element rather than showing an error
4. **Update on Role Change**: Refresh permissions when user roles are updated
5. **Type Safety**: Use TypeScript interfaces for permission slugs to avoid typos

---

## Permission Reference

### Complete Permission List

See the `RolePermissionSeeder.php` file for the complete list of all 100+ permissions, or call:

```http
GET /api/admin/rbac/permissions
```

This will return all permissions grouped by module.

---

## Support

For questions or issues with the RBAC API, contact the backend development team.

**Last Updated:** January 27, 2026

