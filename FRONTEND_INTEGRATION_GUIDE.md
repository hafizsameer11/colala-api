# API Integration Guide for System Notifications & Banners

## 🚀 API Documentation

### **Base URLs**
- **Admin API:** `https://your-api-domain.com/api/admin/`
- **Public API:** `https://your-api-domain.com/api/`

---

## 📱 System Push Notifications API

### **1. Create Notification**
```http
POST /api/admin/notifications
Content-Type: multipart/form-data
Authorization: Bearer {admin_token}
```

**Form Data:**
```javascript
{
  "title": "New Feature Available!",
  "message": "Check out our latest update with amazing new features.",
  "link": "https://app.example.com/features", // Optional
  "attachment": {file}, // Optional image/PDF file
  "audience_type": "specific", // "all", "buyers", "sellers", "specific"
  "target_user_ids": [1, 2, 3, 4], // Required if audience_type is "specific"
  "scheduled_for": "2025-01-20 10:00:00" // Optional, for scheduling
}
```

**Response:**
```json
{
  "status": true,
  "message": "Notification sent successfully",
  "data": {
    "notification": {
      "id": 123,
      "title": "New Feature Available!",
      "message": "Check out our latest update...",
      "link": "https://app.example.com/features",
      "attachment": "https://api.com/storage/notifications/attachments/file.pdf",
      "audience_type": "specific",
      "target_user_ids": [1, 2, 3, 4],
      "status": "sent",
      "sent_at": "2025-01-15T10:30:00Z",
      "created_by": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      },
      "total_recipients": 4,
      "successful_deliveries": 4,
      "failed_deliveries": 0,
      "created_at": "2025-01-15T10:30:00Z"
    }
  }
}
```

### **2. Get All Notifications**
```http
GET /api/admin/notifications?status=sent&audience_type=buyers&search=feature&per_page=20
Authorization: Bearer {admin_token}
```

**Query Parameters:**
- `status`: "draft", "scheduled", "sent", "failed"
- `audience_type`: "all", "buyers", "sellers", "specific"
- `search`: Search in title and message
- `per_page`: Number of items per page (default: 20)

**Response:**
```json
{
  "status": true,
  "data": {
    "notifications": [
      {
        "id": 123,
        "title": "New Feature Available!",
        "message": "Check out our latest update...",
        "link": "https://app.example.com/features",
        "attachment": "https://api.com/storage/notifications/attachments/file.pdf",
        "audience_type": "buyers",
        "target_user_ids": null,
        "status": "sent",
        "scheduled_for": null,
        "sent_at": "2025-01-15T10:30:00Z",
        "created_by": {
          "id": 1,
          "name": "Admin User",
          "email": "admin@example.com"
        },
        "total_recipients": 150,
        "successful_deliveries": 148,
        "failed_deliveries": 2,
        "created_at": "2025-01-15T10:30:00Z"
      }
    ],
    "statistics": {
      "total_notifications": 25,
      "sent_notifications": 20,
      "scheduled_notifications": 3,
      "draft_notifications": 2,
      "total_recipients": 5000,
      "delivered_notifications": 4800,
      "failed_notifications": 200
    },
    "pagination": {
      "current_page": 1,
      "last_page": 2,
      "per_page": 20,
      "total": 25
    }
  }
}
```

### **3. Get Notification Details**
```http
GET /api/admin/notifications/{id}
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "notification": {
      "id": 123,
      "title": "New Feature Available!",
      "message": "Check out our latest update...",
      "link": "https://app.example.com/features",
      "attachment": "https://api.com/storage/notifications/attachments/file.pdf",
      "audience_type": "specific",
      "target_user_ids": [1, 2, 3, 4],
      "status": "sent",
      "sent_at": "2025-01-15T10:30:00Z",
      "created_by": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      },
      "total_recipients": 4,
      "successful_deliveries": 4,
      "failed_deliveries": 0,
      "created_at": "2025-01-15T10:30:00Z"
    },
    "recipients": [
      {
        "id": 1,
        "user": {
          "id": 1,
          "name": "John Doe",
          "email": "john@example.com",
          "role": "buyer"
        },
        "delivery_status": "delivered",
        "delivered_at": "2025-01-15T10:31:00Z",
        "failure_reason": null
      }
    ],
    "statistics": {
      "total_recipients": 4,
      "successful_deliveries": 4,
      "failed_deliveries": 0,
      "delivery_rate": 100.0
    }
  }
}
```

### **4. Get Audience Data**
```http
GET /api/admin/notifications/audience/data?search=john&limit=50
Authorization: Bearer {admin_token}
```

**Query Parameters:**
- `search`: Search in names, emails, store names
- `limit`: Maximum number of users to return (default: 100)

**Response:**
```json
{
  "status": true,
  "data": {
    "buyers": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "user_code": "BUY001",
        "profile_picture": "https://api.com/storage/users/profile1.jpg"
      }
    ],
    "sellers": [
      {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "user_code": "SELL001",
        "store_name": "Jane's Store",
        "profile_picture": "https://api.com/storage/stores/store1.jpg"
      }
    ],
    "statistics": {
      "total_buyers": 150,
      "total_sellers": 25,
      "total_users": 175
    },
    "search_term": "john",
    "limit": 50
  }
}
```

### **5. Update Notification Status**
```http
PUT /api/admin/notifications/{id}/status
Content-Type: application/json
Authorization: Bearer {admin_token}
```

**Request Body:**
```json
{
  "status": "sent"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Notification status updated successfully",
  "data": {
    "notification": {
      "id": 123,
      "status": "sent",
      "sent_at": "2025-01-15T10:30:00Z"
    }
  }
}
```

### **6. Delete Notification**
```http
DELETE /api/admin/notifications/{id}
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "status": true,
  "message": "Notification deleted successfully"
}
```

---

## 🎯 System Banners API

### **1. Create Banner**
```http
POST /api/admin/banners
Content-Type: multipart/form-data
Authorization: Bearer {admin_token}
```

**Form Data:**
```javascript
{
  "title": "Summer Sale Banner",
  "image": {file}, // Required image file
  "link": "https://app.example.com/summer-sale", // Optional
  "audience_type": "all", // "all", "buyers", "sellers", "specific"
  "target_user_ids": [1, 2, 3], // Required if audience_type is "specific"
  "position": "top", // "top", "middle", "bottom"
  "is_active": true,
  "start_date": "2025-01-20 00:00:00", // Optional
  "end_date": "2025-02-20 23:59:59" // Optional
}
```

**Response:**
```json
{
  "status": true,
  "message": "Banner created successfully",
  "data": {
    "banner": {
      "id": 456,
      "title": "Summer Sale Banner",
      "image_url": "https://api.com/storage/banners/banner1.jpg",
      "link": "https://app.example.com/summer-sale",
      "audience_type": "all",
      "target_user_ids": null,
      "position": "top",
      "is_active": true,
      "start_date": "2025-01-20T00:00:00Z",
      "end_date": "2025-02-20T23:59:59Z",
      "created_by": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      },
      "total_views": 0,
      "total_clicks": 0,
      "click_through_rate": 0,
      "is_currently_active": true,
      "created_at": "2025-01-15T10:30:00Z"
    }
  }
}
```

### **2. Get All Banners**
```http
GET /api/admin/banners?is_active=true&audience_type=buyers&position=top&search=summer&per_page=20
Authorization: Bearer {admin_token}
```

**Query Parameters:**
- `is_active`: true/false
- `audience_type`: "all", "buyers", "sellers", "specific"
- `position`: "top", "middle", "bottom"
- `search`: Search in title
- `per_page`: Number of items per page (default: 20)

**Response:**
```json
{
  "status": true,
  "data": {
    "banners": [
      {
        "id": 456,
        "title": "Summer Sale Banner",
        "image_url": "https://api.com/storage/banners/banner1.jpg",
        "link": "https://app.example.com/summer-sale",
        "audience_type": "all",
        "target_user_ids": null,
        "position": "top",
        "is_active": true,
        "start_date": "2025-01-20T00:00:00Z",
        "end_date": "2025-02-20T23:59:59Z",
        "created_by": {
          "id": 1,
          "name": "Admin User",
          "email": "admin@example.com"
        },
        "total_views": 1500,
        "total_clicks": 75,
        "click_through_rate": 5.0,
        "is_currently_active": true,
        "created_at": "2025-01-15T10:30:00Z"
      }
    ],
    "statistics": {
      "total_banners": 10,
      "active_banners": 8,
      "total_views": 15000,
      "total_clicks": 750,
      "average_ctr": 5.0
    },
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 10
    }
  }
}
```

### **3. Get Active Banners (Public)**
```http
GET /api/banners/active
Authorization: Bearer {user_token}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "banners": [
      {
        "id": 456,
        "title": "Summer Sale",
        "image_url": "https://api.com/storage/banners/banner1.jpg",
        "link": "https://app.example.com/summer-sale",
        "position": "top"
      }
    ]
  }
}
```

### **4. Track Banner View**
```http
POST /api/banners/{banner_id}/view
Authorization: Bearer {user_token}
```

**Response:**
```json
{
  "status": true,
  "message": "Banner view tracked"
}
```

### **5. Track Banner Click**
```http
POST /api/banners/{banner_id}/click
Authorization: Bearer {user_token}
```

**Response:**
```json
{
  "status": true,
  "message": "Banner click tracked"
}
```

### **6. Get Banner Analytics**
```http
GET /api/admin/banners/analytics?date_from=2025-01-01&date_to=2025-01-31
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "banner_performance": [
      {
        "id": 456,
        "title": "Summer Sale Banner",
        "total_views": 1500,
        "total_clicks": 75,
        "click_through_rate": 5.0,
        "is_active": true
      }
    ],
    "daily_stats": [
      {
        "date": "2025-01-15",
        "views": 100,
        "clicks": 5
      }
    ],
    "date_range": {
      "from": "2025-01-01",
      "to": "2025-01-31"
    }
  }
}
```

---

## 🔧 Error Responses

### **Validation Error (422)**
```json
{
  "status": false,
  "message": "Validation failed: The title field is required.",
  "errors": {
    "title": ["The title field is required."],
    "message": ["The message field is required."]
  }
}
```

### **Not Found Error (404)**
```json
{
  "status": false,
  "message": "Notification not found"
}
```

### **Server Error (500)**
```json
{
  "status": false,
  "message": "Internal server error"
}
```

---

## 📱 Frontend Integration Examples

### **JavaScript Fetch Examples**

#### **Create Notification**
```javascript
const createNotification = async (formData) => {
  try {
    const response = await fetch('/api/admin/notifications', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
      },
      body: formData // FormData object with file uploads
    });
    
    const result = await response.json();
    return result;
  } catch (error) {
    console.error('Error creating notification:', error);
    throw error;
  }
};
```

#### **Get Audience Data**
```javascript
const getAudienceData = async (search = '', limit = 100) => {
  try {
    const response = await fetch(`/api/admin/notifications/audience/data?search=${search}&limit=${limit}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    return result.data;
  } catch (error) {
    console.error('Error fetching audience data:', error);
    throw error;
  }
};
```

#### **Track Banner View**
```javascript
const trackBannerView = async (bannerId) => {
  try {
    await fetch(`/api/banners/${bannerId}/view`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('user_token')}`,
        'Content-Type': 'application/json'
      }
    });
  } catch (error) {
    console.error('Error tracking banner view:', error);
  }
};
```

### **TypeScript Interfaces**

```typescript
interface Notification {
  id: number;
  title: string;
  message: string;
  link?: string;
  attachment?: string;
  audience_type: 'all' | 'buyers' | 'sellers' | 'specific';
  target_user_ids?: number[];
  status: 'draft' | 'scheduled' | 'sent' | 'failed';
  scheduled_for?: string;
  sent_at?: string;
  created_by: {
    id: number;
    name: string;
    email: string;
  };
  total_recipients: number;
  successful_deliveries: number;
  failed_deliveries: number;
  created_at: string;
}

interface Banner {
  id: number;
  title: string;
  image_url: string;
  link?: string;
  audience_type: 'all' | 'buyers' | 'sellers' | 'specific';
  target_user_ids?: number[];
  position: 'top' | 'middle' | 'bottom';
  is_active: boolean;
  start_date?: string;
  end_date?: string;
  created_by: {
    id: number;
    name: string;
    email: string;
  };
  total_views: number;
  total_clicks: number;
  click_through_rate: number;
  is_currently_active: boolean;
  created_at: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  user_code: string;
  profile_picture?: string;
  store_name?: string; // For sellers
}
```

This API guide provides everything needed for frontend integration! 🎉
