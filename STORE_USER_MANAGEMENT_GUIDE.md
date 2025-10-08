# Store User Management System

## Overview
This system allows store owners to invite and manage users who can access and manage their stores. It provides role-based access control with different permission levels.

## Features

### 🏪 **Store Owner Capabilities**
- Invite users to their store
- Assign roles (Admin, Manager, Staff)
- Set custom permissions
- Remove users from store
- View all store users

### 👥 **User Capabilities**
- Accept store invitations
- Access assigned stores
- View their store permissions
- Join multiple stores

## Database Schema

### `store_users` Table
```sql
CREATE TABLE store_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    permissions JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    invited_at TIMESTAMP NULL,
    joined_at TIMESTAMP NULL,
    invited_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_store_user (store_id, user_id),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
);
```

## Role System

### **Admin Role**
- **Description**: Full access to all store features
- **Permissions**:
  - `manage_products` - Create, edit, delete products
  - `manage_orders` - View and manage orders
  - `manage_customers` - View customer information
  - `manage_analytics` - Access analytics dashboard
  - `manage_settings` - Store settings management
  - `manage_users` - Invite and manage store users
  - `manage_inventory` - Inventory management
  - `manage_promotions` - Promotional campaigns

### **Manager Role**
- **Description**: Access to most store features except user management
- **Permissions**:
  - `manage_products`
  - `manage_orders`
  - `manage_customers`
  - `manage_analytics`
  - `manage_inventory`
  - `manage_promotions`

### **Staff Role**
- **Description**: Basic access to orders and customers
- **Permissions**:
  - `manage_orders`
  - `manage_customers`
  - `view_analytics`

## API Endpoints

### **Store Owner Endpoints** (Seller Routes)

#### **Get Store Users**
```http
GET /api/seller/stores/{storeId}/users
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "message": "Store users retrieved successfully",
    "data": [
        {
            "id": 1,
            "user_id": 2,
            "name": "John Doe",
            "email": "john@example.com",
            "profile_picture": "path/to/image.jpg",
            "role": "admin",
            "permissions": ["manage_products", "manage_orders", ...],
            "is_active": true,
            "invited_at": "2025-01-07T10:00:00Z",
            "joined_at": "2025-01-07T10:30:00Z",
            "invited_by": "Store Owner"
        }
    ]
}
```

#### **Invite User to Store**
```http
POST /api/seller/stores/{storeId}/users/invite
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "password123",
    "role": "manager",
    "permissions": ["manage_products", "manage_orders"]
}
```

**Response:**
```json
{
    "status": true,
    "message": "User invited successfully",
    "data": {
        "id": 2,
        "user_id": 3,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "role": "manager",
        "permissions": ["manage_products", "manage_orders"],
        "invited_at": "2025-01-07T10:00:00Z"
    }
}
```

#### **Update User Role/Permissions**
```http
PUT /api/seller/stores/{storeId}/users/{userId}
Authorization: Bearer {token}
Content-Type: application/json

{
    "role": "staff",
    "permissions": ["manage_orders"],
    "is_active": true
}
```

#### **Remove User from Store**
```http
DELETE /api/seller/stores/{storeId}/users/{userId}
Authorization: Bearer {token}
```

#### **Get Available Roles**
```http
GET /api/seller/stores/users/roles
Authorization: Bearer {token}
```

### **User Endpoints** (Buyer Routes)

#### **Get User's Stores**
```http
GET /api/stores
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "message": "User stores retrieved successfully",
    "data": [
        {
            "id": 1,
            "store_id": 5,
            "store_name": "Tech Store",
            "store_email": "tech@store.com",
            "profile_image": "path/to/image.jpg",
            "role": "manager",
            "permissions": ["manage_products", "manage_orders"],
            "joined_at": "2025-01-07T10:30:00Z"
        }
    ]
}
```

#### **Join Store** (Accept Invitation)
```http
POST /api/stores/{storeId}/join
Authorization: Bearer {token}
```

#### **Check Store Permission**
```http
GET /api/stores/{storeId}/permission?permission=manage_products
Authorization: Bearer {token}
```

## Middleware Usage

### **Store Access Middleware**
Protect routes that require store access:

```php
Route::middleware(['auth:sanctum', 'store.access:manage_products'])->group(function () {
    // Routes that require manage_products permission
});
```

**Available Middleware Options:**
- `store.access` - Basic store access
- `store.access:manage_products` - Specific permission
- `store.access:admin` - Admin role only

## Models and Relationships

### **StoreUser Model**
```php
// Check if user has permission
$storeUser->hasPermission('manage_products');

// Check if user has role
$storeUser->hasRole('admin');

// Get all permissions (role + custom)
$storeUser->getAllPermissions();

// Check if user can access store
$storeUser->canAccessStore();
```

### **User Model Extensions**
```php
// Check if user has store access
$user->hasStoreAccess($storeId);

// Get user's role in store
$user->getStoreRole($storeId);

// Check store permission
$user->hasStorePermission($storeId, 'manage_products');

// Get user's stores
$user->stores;
```

## Business Logic

### **Invitation Process**
1. Store owner invites user with email, name, password, role
2. If user doesn't exist, create new user account
3. Create `StoreUser` relationship with `invited_at` timestamp
4. Send invitation email (optional)
5. User accepts invitation by calling join endpoint
6. Set `joined_at` timestamp and activate user

### **Permission System**
1. **Role-based permissions**: Each role has default permissions
2. **Custom permissions**: Additional permissions can be assigned
3. **Permission checking**: Combines role and custom permissions
4. **Middleware protection**: Routes protected by permission middleware

### **Access Control**
- Users must be active (`is_active = true`)
- Users must have joined (`joined_at` not null)
- Store owners cannot be removed from their own store
- Permission checks are enforced at middleware level

## Security Features

### **Authentication**
- Sanctum token-based authentication
- Store-specific access control
- Role-based permission system

### **Authorization**
- Middleware-based route protection
- Permission-based access control
- Store ownership validation

### **Data Protection**
- Foreign key constraints
- Cascade delete for data integrity
- Unique constraints prevent duplicate relationships

## Usage Examples

### **Frontend Integration**

#### **Store Owner Dashboard**
```javascript
// Get store users
const response = await fetch('/api/seller/stores/1/users', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
const users = await response.json();

// Invite new user
const inviteResponse = await fetch('/api/seller/stores/1/users/invite', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        name: 'New User',
        email: 'user@example.com',
        password: 'password123',
        role: 'manager'
    })
});
```

#### **User Store Access**
```javascript
// Get user's stores
const stores = await fetch('/api/stores', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});

// Join store
const joinResponse = await fetch('/api/stores/1/join', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`
    }
});
```

## Error Handling

### **Common Error Responses**
```json
{
    "status": false,
    "message": "You do not have access to this store",
    "data": null
}
```

### **Validation Errors**
```json
{
    "status": false,
    "message": "The email field is required",
    "data": null
}
```

## Best Practices

### **Store Owner**
1. **Role Assignment**: Assign appropriate roles based on user responsibilities
2. **Permission Management**: Use custom permissions for specific needs
3. **User Monitoring**: Regularly review store user access
4. **Security**: Remove inactive users promptly

### **Store Users**
1. **Accept Invitations**: Join stores promptly after invitation
2. **Permission Awareness**: Understand your role and permissions
3. **Security**: Use strong passwords and secure authentication

### **Development**
1. **Middleware Usage**: Protect sensitive routes with appropriate middleware
2. **Permission Checks**: Always verify permissions before allowing actions
3. **Error Handling**: Provide clear error messages for access issues
4. **Testing**: Test all role and permission combinations

This system provides a complete role-based access control solution for multi-user store management! 🏪👥✨
