# Notification System Implementation Guide

## 🚀 Overview

The notification system has been implemented throughout the Colala platform to keep users informed about important events. Notifications are sent automatically when key actions occur in the system.

## 📱 Notification Events

### **1. Order Management**
- **Order Placed**: Notifies buyer and store owners when an order is successfully placed
- **Payment Confirmed**: Notifies buyer and store owners when payment is confirmed
- **Order Status Updated**: Notifies both parties when order status changes (processing, shipped, delivered, etc.)

### **2. Chat Messages**
- **New Message**: Notifies the recipient when a new chat message is received
- **Message Preview**: Shows a preview of the message content (truncated to 50 characters)

### **3. System Events**
- **Wallet Transactions**: Notifications for wallet top-ups, transfers, and payments
- **Account Updates**: Profile changes, store updates, etc.

## 🔧 Implementation Details

### **UserNotificationHelper**
```php
use App\Helpers\UserNotificationHelper;

// Send notification
UserNotificationHelper::notify($user_id, $title, $content);
```

### **Database Structure**
```sql
user_notifications:
- id (primary key)
- user_id (foreign key to users)
- title (notification title)
- content (notification message)
- is_read (boolean, default false)
- created_at, updated_at
```

## 📋 API Endpoints

### **Get User Notifications**
```http
GET /api/notifications?status=unread&per_page=20
Authorization: Bearer {user_token}
```

**Query Parameters:**
- `status`: "read", "unread", or null for all
- `per_page`: Number of notifications per page (default: 20)

**Response:**
```json
{
  "status": true,
  "data": {
    "notifications": [
      {
        "id": 1,
        "user_id": 123,
        "title": "Order Placed Successfully",
        "content": "Your order #COL-20250115-123456 has been placed successfully. Total amount: ₦15,000.00",
        "is_read": false,
        "created_at": "2025-01-15T10:30:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 20,
      "total": 45
    },
    "unread_count": 12
  }
}
```

### **Mark Notification as Read**
```http
PUT /api/notifications/{id}/read
Authorization: Bearer {user_token}
```

### **Mark All Notifications as Read**
```http
PUT /api/notifications/mark-all-read
Authorization: Bearer {user_token}
```

### **Delete Notification**
```http
DELETE /api/notifications/{id}
Authorization: Bearer {user_token}
```

### **Get Notification Statistics**
```http
GET /api/notifications/stats
Authorization: Bearer {user_token}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "total_notifications": 45,
    "unread_notifications": 12,
    "read_notifications": 33
  }
}
```

## 🎯 Notification Types

### **Order Notifications**
- **Buyer**: "Order Placed Successfully", "Payment Confirmed", "Order Status Update"
- **Seller**: "New Order Received", "Payment Received", "Order Status Updated"

### **Chat Notifications**
- **Buyer**: "New Message from Store"
- **Seller**: "New Message from Customer"

### **System Notifications**
- **Wallet**: "Payment Confirmed", "Wallet Topped Up"
- **Account**: "Profile Updated", "Store Approved"

## 🔄 Notification Flow

### **Order Placement Flow**
1. User places order → `CheckoutService::place()`
2. Order created → `sendOrderNotifications()`
3. Notifications sent to:
   - Buyer: "Order Placed Successfully"
   - Each store owner: "New Order Received"

### **Chat Message Flow**
1. User sends message → `ChatService::sendMessage()`
2. Message created → `sendChatNotification()`
3. Notification sent to recipient with message preview

### **Order Status Update Flow**
1. Admin/seller updates status → `AdminOrderManagementController::updateOrderStatus()`
2. Status updated → `sendOrderStatusNotification()`
3. Notifications sent to both buyer and seller

## 📱 Frontend Integration

### **React/Next.js Example**
```javascript
// Fetch notifications
const fetchNotifications = async (status = null, page = 1) => {
  const params = new URLSearchParams();
  if (status) params.append('status', status);
  params.append('per_page', 20);
  params.append('page', page);

  const response = await fetch(`/api/notifications?${params}`, {
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('user_token')}`,
      'Content-Type': 'application/json'
    }
  });

  return await response.json();
};

// Mark as read
const markAsRead = async (notificationId) => {
  const response = await fetch(`/api/notifications/${notificationId}/read`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('user_token')}`,
      'Content-Type': 'application/json'
    }
  });

  return await response.json();
};

// Mark all as read
const markAllAsRead = async () => {
  const response = await fetch('/api/notifications/mark-all-read', {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('user_token')}`,
      'Content-Type': 'application/json'
    }
  });

  return await response.json();
};
```

### **Notification Component Example**
```jsx
import React, { useState, useEffect } from 'react';

const NotificationCenter = () => {
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchNotifications();
  }, []);

  const fetchNotifications = async () => {
    try {
      const response = await fetch('/api/notifications', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('user_token')}`,
        }
      });
      const data = await response.json();
      setNotifications(data.data.notifications);
      setUnreadCount(data.data.unread_count);
    } catch (error) {
      console.error('Error fetching notifications:', error);
    } finally {
      setLoading(false);
    }
  };

  const markAsRead = async (id) => {
    try {
      await fetch(`/api/notifications/${id}/read`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('user_token')}`,
        }
      });
      fetchNotifications(); // Refresh notifications
    } catch (error) {
      console.error('Error marking as read:', error);
    }
  };

  if (loading) return <div>Loading notifications...</div>;

  return (
    <div className="notification-center">
      <div className="header">
        <h3>Notifications ({unreadCount} unread)</h3>
        <button onClick={markAllAsRead}>Mark All Read</button>
      </div>
      
      <div className="notifications-list">
        {notifications.map((notification) => (
          <div 
            key={notification.id} 
            className={`notification ${!notification.is_read ? 'unread' : ''}`}
            onClick={() => markAsRead(notification.id)}
          >
            <h4>{notification.title}</h4>
            <p>{notification.content}</p>
            <small>{new Date(notification.created_at).toLocaleString()}</small>
          </div>
        ))}
      </div>
    </div>
  );
};

export default NotificationCenter;
```

## 🎨 CSS Styles

```css
.notification-center {
  max-width: 400px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.notification {
  padding: 12px 16px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: background-color 0.2s;
}

.notification:hover {
  background-color: #f8f9fa;
}

.notification.unread {
  background-color: #e3f2fd;
  border-left: 4px solid #2196f3;
}

.notification h4 {
  margin: 0 0 4px 0;
  font-size: 14px;
  font-weight: 600;
  color: #333;
}

.notification p {
  margin: 0 0 8px 0;
  font-size: 13px;
  color: #666;
  line-height: 1.4;
}

.notification small {
  font-size: 11px;
  color: #999;
}
```

## 🔧 Configuration

### **Notification Settings**
- **Max message preview**: 50 characters
- **Default pagination**: 20 notifications per page
- **Auto-mark as read**: When user clicks on notification

### **Database Migration**
```bash
php artisan migrate
```

### **Testing Notifications**
```php
// Test notification
UserNotificationHelper::notify(
    1, // user_id
    'Test Notification',
    'This is a test notification message'
);
```

## 📊 Analytics

### **Notification Statistics**
- Total notifications sent
- Read vs unread ratio
- Most common notification types
- User engagement metrics

### **Performance Considerations**
- Notifications are created synchronously
- Consider queue for high-volume scenarios
- Index on `user_id` and `is_read` for performance

## 🚀 Future Enhancements

### **Planned Features**
1. **Push Notifications**: Mobile app integration
2. **Email Notifications**: Optional email alerts
3. **Notification Preferences**: User-controlled settings
4. **Real-time Updates**: WebSocket integration
5. **Notification Templates**: Customizable message templates

### **Advanced Features**
1. **Notification Scheduling**: Send notifications at specific times
2. **Bulk Notifications**: Send to multiple users
3. **Notification Categories**: Group by type (orders, messages, system)
4. **Rich Notifications**: Include images, buttons, actions

This comprehensive notification system ensures users stay informed about all important events in the Colala platform! 🎉

