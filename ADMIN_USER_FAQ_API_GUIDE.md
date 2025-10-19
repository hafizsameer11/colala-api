# Admin User Management & FAQ Management API Guide

This guide covers the complete admin user management and FAQ management systems for the Colala Mall admin panel.

## Overview

### Admin User Management
The admin user management system allows administrators to:
- View and manage all admin users (admin, moderator, super_admin roles)
- Create, update, and delete admin users
- Search and filter admin users
- Perform bulk actions
- View admin user statistics

### FAQ Management
The FAQ management system allows administrators to:
- Manage FAQs by category (general, buyer, seller)
- Create, update, and delete FAQs
- Update FAQ categories
- Perform bulk actions on FAQs
- View FAQ statistics

## API Endpoints

All endpoints are prefixed with `/api/admin/` and require authentication.

## Admin User Management Endpoints

### 1. Get All Admin Users

**GET** `/api/admin/admin-users`

Retrieve all admin users with filtering and pagination.

#### Query Parameters:
- `search` (optional): Search in name, email, or phone
- `role` (optional): Filter by role (`admin`, `moderator`, `super_admin`, `all`)
- `status` (optional): Filter by status (`active`, `inactive`, `all`)

#### Response:
```json
{
  "status": "success",
  "message": "Admin users retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "full_name": "John Admin",
        "user_name": "john_admin",
        "email": "john@admin.com",
        "phone": "+1234567890",
        "profile_picture": "profile.jpg",
        "role": "admin",
        "is_active": true,
        "wallet_balance": "1500.00",
        "created_at": "2025-01-15 10:30:00"
      }
    ],
    "first_page_url": "http://localhost/api/admin/admin-users?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://localhost/api/admin/admin-users?page=5",
    "links": [...],
    "next_page_url": "http://localhost/api/admin/admin-users?page=2",
    "path": "http://localhost/api/admin/admin-users",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 75
  }
}
```

### 2. Get Admin User Statistics

**GET** `/api/admin/admin-users/stats`

Get comprehensive admin user statistics.

#### Response:
```json
{
  "status": "success",
  "message": "Admin user statistics retrieved successfully",
  "data": {
    "total_admins": {
      "value": 25,
      "increase": 5,
      "icon": "users",
      "color": "red"
    },
    "active_admins": {
      "value": 20,
      "increase": 5,
      "icon": "users",
      "color": "red"
    },
    "new_admins": {
      "value": 3,
      "increase": 5,
      "icon": "users",
      "color": "red"
    }
  }
}
```

### 3. Search Admin Users

**GET** `/api/admin/admin-users/search`

Search admin users by name, email, or phone.

#### Query Parameters:
- `search` (required): Search term (minimum 2 characters)

#### Response:
```json
{
  "status": "success",
  "message": "Search results retrieved successfully",
  "data": [
    {
      "id": 1,
      "full_name": "John Admin",
      "user_name": "john_admin",
      "email": "john@admin.com",
      "phone": "+1234567890",
      "profile_picture": "profile.jpg",
      "role": "admin",
      "wallet_balance": "1500.00"
    }
  ]
}
```

### 4. Bulk Actions on Admin Users

**POST** `/api/admin/admin-users/bulk-action`

Perform bulk actions on multiple admin users.

#### Request Body:
```json
{
  "user_ids": [1, 2, 3, 4, 5],
  "action": "activate"
}
```

#### Available Actions:
- `activate`: Activate selected users
- `deactivate`: Deactivate selected users
- `delete`: Delete selected users

#### Response:
```json
{
  "status": "success",
  "message": "Admin users activated successfully",
  "data": null
}
```

### 5. Get Admin User Profile

**GET** `/api/admin/admin-users/{id}/profile`

Get detailed admin user profile information.

#### Response:
```json
{
  "status": "success",
  "message": "Admin user profile retrieved successfully",
  "data": {
    "id": 1,
    "full_name": "John Admin",
    "user_name": "john_admin",
    "email": "john@admin.com",
    "phone": "+1234567890",
    "country": "Nigeria",
    "state": "Lagos",
    "profile_picture": "http://localhost/storage/profile.jpg",
    "role": "admin",
    "last_login": "15/01/25 - 10:30 AM",
    "account_created_at": "01/01/25 - 09:00 AM",
    "loyalty_points": 150,
    "is_blocked": false,
    "wallet": {
      "shopping_balance": "1,500",
      "escrow_balance": "500"
    },
    "recent_activities": [
      {
        "id": 1,
        "description": "User logged in",
        "created_at": "15/01/25 - 10:30 AM"
      }
    ]
  }
}
```

### 6. Get Admin User Details

**GET** `/api/admin/admin-users/{id}/details`

Get comprehensive admin user details.

#### Response:
```json
{
  "status": "success",
  "message": "Admin user details retrieved successfully",
  "data": {
    "id": 1,
    "full_name": "John Admin",
    "user_name": "john_admin",
    "email": "john@admin.com",
    "phone": "+1234567890",
    "profile_picture": "profile.jpg",
    "role": "admin",
    "is_active": true,
    "country": "Nigeria",
    "state": "Lagos",
    "user_code": "COL-82K4TZ",
    "referral_code": "REF123",
    "wallet": {
      "shopping_balance": "1,500.00",
      "reward_balance": "200.00",
      "referral_balance": "500.00",
      "loyalty_points": 150
    },
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 10:30:00"
  }
}
```

### 7. Create Admin User

**POST** `/api/admin/admin-users`

Create a new admin user.

#### Request Body:
```json
{
  "full_name": "Jane Admin",
  "user_name": "jane_admin",
  "email": "jane@admin.com",
  "phone": "+1234567891",
  "password": "password123",
  "country": "Nigeria",
  "state": "Lagos",
  "role": "admin",
  "referral_code": "REF456",
  "profile_picture": "file_upload"
}
```

#### Response:
```json
{
  "status": "success",
  "message": "Admin user created successfully",
  "data": {
    "id": 2,
    "full_name": "Jane Admin",
    "user_name": "jane_admin",
    "email": "jane@admin.com",
    "phone": "+1234567891",
    "role": "admin",
    "is_active": true,
    "created_at": "2025-01-15 10:30:00"
  }
}
```

### 8. Update Admin User

**PUT** `/api/admin/admin-users/{id}`

Update admin user information.

#### Request Body:
```json
{
  "full_name": "Jane Admin Updated",
  "email": "jane.updated@admin.com",
  "phone": "+1234567892",
  "role": "moderator",
  "is_active": true,
  "country": "Nigeria",
  "state": "Abuja"
}
```

#### Response:
```json
{
  "status": "success",
  "message": "Admin user updated successfully",
  "data": {
    "id": 2,
    "full_name": "Jane Admin Updated",
    "user_name": "jane_admin",
    "email": "jane.updated@admin.com",
    "phone": "+1234567892",
    "role": "moderator",
    "is_active": true,
    "updated_at": "2025-01-15 11:00:00"
  }
}
```

### 9. Delete Admin User

**DELETE** `/api/admin/admin-users/{id}`

Delete an admin user.

#### Response:
```json
{
  "status": "success",
  "message": "Admin user deleted successfully",
  "data": null
}
```

## FAQ Management Endpoints

### 1. Get FAQ Categories

**GET** `/api/admin/faq/categories`

Get all FAQ categories with FAQ counts.

#### Response:
```json
{
  "status": "success",
  "message": "FAQ categories retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "general",
      "video": "https://youtu.be/3upoJn31wnk?si=jNA6YSEU2i3kUnqP",
      "is_active": 1,
      "faqs_count": 15,
      "created_at": "2025-01-15 10:30:00",
      "updated_at": "2025-01-15 10:30:00"
    },
    {
      "id": 2,
      "title": "buyer",
      "video": null,
      "is_active": 1,
      "faqs_count": 8,
      "created_at": "2025-01-15 10:30:00",
      "updated_at": "2025-01-15 10:30:00"
    },
    {
      "id": 3,
      "title": "seller",
      "video": null,
      "is_active": 1,
      "faqs_count": 12,
      "created_at": "2025-01-15 10:30:00",
      "updated_at": "2025-01-15 10:30:00"
    }
  ]
}
```

### 2. Get FAQ Statistics

**GET** `/api/admin/faq/statistics`

Get comprehensive FAQ statistics.

#### Response:
```json
{
  "status": "success",
  "message": "FAQ statistics retrieved successfully",
  "data": {
    "total_faqs": 35,
    "active_faqs": 30,
    "inactive_faqs": 5,
    "categories_stats": [
      {
        "category": "general",
        "total_faqs": 15,
        "active_faqs": 12,
        "inactive_faqs": 3
      },
      {
        "category": "buyer",
        "total_faqs": 8,
        "active_faqs": 8,
        "inactive_faqs": 0
      },
      {
        "category": "seller",
        "total_faqs": 12,
        "active_faqs": 10,
        "inactive_faqs": 2
      }
    ]
  }
}
```

### 3. Get FAQs by Category

**GET** `/api/admin/faq/general`
**GET** `/api/admin/faq/buyer`
**GET** `/api/admin/faq/seller`

Get FAQs for specific categories.

#### Query Parameters:
- `search` (optional): Search in question or answer
- `status` (optional): Filter by status (`active`, `inactive`, `all`)

#### Response:
```json
{
  "status": "success",
  "message": "FAQs for general category retrieved successfully",
  "data": {
    "faqs": {
      "current_page": 1,
      "data": [
        {
          "id": 1,
          "question": "How do I create an account?",
          "answer": "To create an account, click on the sign up button...",
          "is_active": true,
          "category": {
            "id": 1,
            "title": "general",
            "video": "https://youtu.be/3upoJn31wnk?si=jNA6YSEU2i3kUnqP"
          },
          "created_at": "2025-01-15 10:30:00",
          "updated_at": "2025-01-15 10:30:00"
        }
      ],
      "first_page_url": "http://localhost/api/admin/faq/general?page=1",
      "from": 1,
      "last_page": 2,
      "last_page_url": "http://localhost/api/admin/faq/general?page=2",
      "links": [...],
      "next_page_url": "http://localhost/api/admin/faq/general?page=2",
      "path": "http://localhost/api/admin/faq/general",
      "per_page": 15,
      "prev_page_url": null,
      "to": 15,
      "total": 15
    },
    "category": "general",
    "pagination": {
      "current_page": 1,
      "last_page": 2,
      "per_page": 15,
      "total": 15
    }
  }
}
```

### 4. Get FAQ Details

**GET** `/api/admin/faq/{id}/details`

Get detailed FAQ information.

#### Response:
```json
{
  "status": "success",
  "message": "FAQ details retrieved successfully",
  "data": {
    "id": 1,
    "question": "How do I create an account?",
    "answer": "To create an account, click on the sign up button and fill in your details...",
    "is_active": true,
    "category": {
      "id": 1,
      "title": "general",
      "video": "https://youtu.be/3upoJn31wnk?si=jNA6YSEU2i3kUnqP",
      "is_active": 1
    },
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 10:30:00"
  }
}
```

### 5. Create FAQ

**POST** `/api/admin/faq`

Create a new FAQ.

#### Request Body:
```json
{
  "faq_category_id": 1,
  "question": "How do I reset my password?",
  "answer": "To reset your password, click on forgot password and follow the instructions...",
  "is_active": true
}
```

#### Response:
```json
{
  "status": "success",
  "message": "FAQ created successfully",
  "data": {
    "id": 4,
    "question": "How do I reset my password?",
    "answer": "To reset your password, click on forgot password and follow the instructions...",
    "is_active": true,
    "category": {
      "id": 1,
      "title": "general"
    }
  }
}
```

### 6. Update FAQ

**PUT** `/api/admin/faq/{id}`

Update FAQ information.

#### Request Body:
```json
{
  "question": "How do I reset my password? (Updated)",
  "answer": "To reset your password, go to the login page and click on 'Forgot Password'...",
  "is_active": false
}
```

#### Response:
```json
{
  "status": "success",
  "message": "FAQ updated successfully",
  "data": {
    "id": 4,
    "question": "How do I reset my password? (Updated)",
    "answer": "To reset your password, go to the login page and click on 'Forgot Password'...",
    "is_active": false,
    "category": {
      "id": 1,
      "title": "general"
    }
  }
}
```

### 7. Delete FAQ

**DELETE** `/api/admin/faq/{id}`

Delete an FAQ.

#### Response:
```json
{
  "status": "success",
  "message": "FAQ deleted successfully",
  "data": null
}
```

### 8. Bulk Actions on FAQs

**POST** `/api/admin/faq/bulk-action`

Perform bulk actions on multiple FAQs.

#### Request Body:
```json
{
  "faq_ids": [1, 2, 3, 4, 5],
  "action": "activate"
}
```

#### Available Actions:
- `activate`: Activate selected FAQs
- `deactivate`: Deactivate selected FAQs
- `delete`: Delete selected FAQs

#### Response:
```json
{
  "status": "success",
  "message": "FAQs activated successfully",
  "data": null
}
```

### 9. Update FAQ Category

**PUT** `/api/admin/faq/categories/{id}`

Update FAQ category information.

#### Request Body:
```json
{
  "title": "general",
  "video": "https://youtu.be/new-video-link",
  "is_active": true
}
```

#### Response:
```json
{
  "status": "success",
  "message": "FAQ category updated successfully",
  "data": {
    "id": 1,
    "title": "general",
    "video": "https://youtu.be/new-video-link",
    "is_active": true,
    "faqs_count": 15
  }
}
```

## Role Values

### Admin User Roles:
- `admin`: Standard admin user
- `moderator`: Moderator with limited permissions
- `super_admin`: Super admin with full permissions

## Status Values

- `active`: User/FAQ is active
- `inactive`: User/FAQ is inactive

## Error Responses

All endpoints return appropriate HTTP status codes and error messages:

```json
{
  "status": "error",
  "message": "Admin user not found.",
  "data": null
}
```

## Authentication

All endpoints require authentication via Sanctum token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Usage Examples

### Get General FAQs
```bash
curl -X GET "https://api.colala.com/admin/faq/general" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Create Admin User
```bash
curl -X POST "https://api.colala.com/admin/admin-users" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "Jane Admin",
    "user_name": "jane_admin",
    "email": "jane@admin.com",
    "phone": "+1234567891",
    "password": "password123",
    "country": "Nigeria",
    "state": "Lagos",
    "role": "admin"
  }'
```

### Bulk Activate FAQs
```bash
curl -X POST "https://api.colala.com/admin/faq/bulk-action" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "faq_ids": [1, 2, 3],
    "action": "activate"
  }'
```

