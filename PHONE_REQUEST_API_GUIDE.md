# 📞 Phone Number Request API Guide (Notification-Based)

## Overview

This system allows buyers to request phone numbers from sellers through a notification-based system. When a buyer requests a phone number, the seller receives a notification. If approved, the buyer receives a notification with the embedded phone number.

---

## 🔄 Complete Flow

### Step 1: Buyer Requests Phone Number
1. Buyer clicks "Request Phone Number" button for a store
2. System creates a `RevealPhone` record with `is_revealed = false`
3. Seller receives a notification: "John Doe has requested your phone number"

### Step 2: Seller Reviews Request
1. Seller sees notification with phone request
2. Seller can either:
   - **Approve**: Buyer gets notification with phone number
   - **Decline**: Buyer gets notification that request was declined

### Step 3: Phone Number Shared (If Approved)
1. System updates `RevealPhone` to `is_revealed = true`
2. Buyer receives notification with embedded phone number
3. Buyer can access phone number anytime from their revealed phone numbers list

---

## 🎯 API Endpoints

### **BUYER ENDPOINTS**

#### 1. Request Phone Number
**Endpoint:** `POST /api/buyer/phone-request`

**Authentication:** Required (`auth:sanctum`)

**Request Body:**
```json
{
  "store_id": 123
}
```

**Success Response (201):**
```json
{
  "status": "success",
  "message": "Phone number request sent successfully",
  "data": {
    "reveal_phone_id": 12,
    "is_revealed": false
  }
}
```

**Possible Responses:**
- **Already Revealed (200):**
```json
{
  "status": "success",
  "message": "Phone number already shared",
  "data": {
    "is_revealed": true,
    "phone_number": "+1234567890"
  }
}
```

- **Already Pending (200):**
```json
{
  "status": "success",
  "message": "Phone number request already pending",
  "data": {
    "is_revealed": false
  }
}
```

---

#### 2. Check Phone Request Status
**Endpoint:** `GET /api/buyer/phone-request/status`

**Authentication:** Required (`auth:sanctum`)

**Query Parameters:**
- `store_id` (required): Store ID

**Example:** `GET /api/buyer/phone-request/status?store_id=123`

**Success Response (200):**

**No Request:**
```json
{
  "status": "success",
  "data": {
    "has_request": false,
    "is_revealed": false
  }
}
```

**Request Pending:**
```json
{
  "status": "success",
  "data": {
    "has_request": true,
    "is_revealed": false
  }
}
```

**Phone Number Revealed:**
```json
{
  "status": "success",
  "data": {
    "has_request": true,
    "is_revealed": true,
    "phone_number": "+1234567890"
  }
}
```

---

#### 3. Get All Revealed Phone Numbers
**Endpoint:** `GET /api/buyer/phone-request/revealed`

**Authentication:** Required (`auth:sanctum`)

**Success Response (200):**
```json
{
  "status": "success",
  "data": {
    "total": 3,
    "phone_numbers": [
      {
        "id": 12,
        "store": {
          "id": 123,
          "name": "Tech Store",
          "phone_number": "+1234567890",
          "profile_image": "https://domain.com/storage/stores/profile.jpg"
        },
        "revealed_at": "27-10-2025 10:30 AM"
      }
    ]
  }
}
```

---

### **SELLER ENDPOINTS**

#### 4. Get Pending Phone Requests
**Endpoint:** `GET /api/seller/phone-requests`

**Authentication:** Required (`auth:sanctum`)

**Success Response (200):**
```json
{
  "status": "success",
  "data": {
    "total": 3,
    "requests": [
      {
        "id": 12,
        "buyer": {
          "id": 78,
          "name": "John Doe",
          "email": "john@example.com",
          "profile_picture": "https://domain.com/storage/users/profile.jpg"
        },
        "store": {
          "id": 123,
          "name": "My Store"
        },
        "is_revealed": false,
        "requested_at": "27-10-2025 10:30 AM"
      }
    ]
  }
}
```

---

#### 5. Approve Phone Request
**Endpoint:** `POST /api/seller/phone-requests/{revealPhoneId}/approve`

**Authentication:** Required (`auth:sanctum`)

**Path Parameters:**
- `revealPhoneId`: ID of the reveal phone request

**Example:** `POST /api/seller/phone-requests/12/approve`

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Phone number shared successfully",
  "data": {
    "phone_number": "+1234567890",
    "is_revealed": true
  }
}
```

**What Happens:**
1. ✅ Updates `RevealPhone` to `is_revealed = true`
2. ✅ Creates notification for buyer with type `phone_approved`
3. ✅ Notification includes embedded phone number in `data.phone_number`

**Error Responses:**

**Not Found (404):**
```json
{
  "status": "error",
  "message": "Phone request not found"
}
```

**Unauthorized (403):**
```json
{
  "status": "error",
  "message": "Unauthorized. This request is not for your store."
}
```

---

#### 6. Decline Phone Request
**Endpoint:** `POST /api/seller/phone-requests/{revealPhoneId}/decline`

**Authentication:** Required (`auth:sanctum`)

**Path Parameters:**
- `revealPhoneId`: ID of the reveal phone request

**Example:** `POST /api/seller/phone-requests/12/decline`

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Phone number request declined",
  "data": {
    "is_revealed": false
  }
}
```

**What Happens:**
1. ✅ Creates notification for buyer with type `phone_declined`
2. ✅ Deletes the `RevealPhone` record

---

## 📊 Database Structure

### `reveal_phones` Table
```sql
CREATE TABLE reveal_phones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    is_revealed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_store (user_id, store_id)
);
```

**Key Features:**
- No `chat_id` dependency
- Unique constraint on `user_id` + `store_id` (prevents duplicate requests)
- Cascade deletion when user or store is deleted

---

## 🔔 Notification Types

### 1. Phone Request Notification (Sent to Seller)
```json
{
  "id": 1,
  "user_id": 45,
  "title": "Phone Number Request",
  "content": "John Doe has requested your phone number for Tech Store. Request ID: 12",
  "is_read": false,
  "created_at": "2025-10-27T10:30:00.000000Z",
  "updated_at": "2025-10-27T10:30:00.000000Z"
}
```

**Content Format:** `{buyer_name} has requested your phone number for {store_name}. Request ID: {reveal_phone_id}`

### 2. Phone Approved Notification (Sent to Buyer)
```json
{
  "id": 2,
  "user_id": 78,
  "title": "Phone Number Approved",
  "content": "Tech Store has approved your phone number request. Phone: +1234567890",
  "is_read": false,
  "created_at": "2025-10-27T10:35:00.000000Z",
  "updated_at": "2025-10-27T10:35:00.000000Z"
}
```

**Content Format:** `{store_name} has approved your phone number request. Phone: {phone_number}`

**✅ PHONE NUMBER EMBEDDED IN CONTENT**

### 3. Phone Declined Notification (Sent to Buyer)
```json
{
  "id": 3,
  "user_id": 78,
  "title": "Phone Number Request Declined",
  "content": "Tech Store has declined your phone number request. Please contact them through chat.",
  "is_read": false,
  "created_at": "2025-10-27T10:40:00.000000Z",
  "updated_at": "2025-10-27T10:40:00.000000Z"
}
```

**Content Format:** `{store_name} has declined your phone number request. Please contact them through chat.`

---

## 🔒 Security & Validation

### Authorization Rules
1. **Buyer Requests:**
   - Must be authenticated
   - Cannot request phone from same store twice (database constraint)
   - Can check status of their own requests only

2. **Seller Actions:**
   - Must be authenticated
   - Can only approve/decline requests for their own stores
   - Cannot approve already approved requests

### Data Validation
- `store_id` must exist in database
- `reveal_phone_id` must be valid and belong to seller's store
- Unique constraint prevents duplicate requests

---

## 🎨 Frontend Implementation Guide

### Buyer Side

#### 1. Request Phone Number
```javascript
const requestPhoneNumber = async (storeId) => {
  const response = await fetch('/api/buyer/phone-request', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ store_id: storeId })
  });
  
  const data = await response.json();
  
  if (data.status === 'success') {
    showSuccessToast('Phone number request sent!');
    // Show "Request Pending" status
  }
};
```

#### 2. Check Request Status
```javascript
const checkPhoneStatus = async (storeId) => {
  const response = await fetch(`/api/buyer/phone-request/status?store_id=${storeId}`, {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  const data = await response.json();
  
  if (data.data.is_revealed) {
    // Show phone number
    showPhoneNumber(data.data.phone_number);
  } else if (data.data.has_request) {
    // Show "Request Pending"
    showPendingStatus();
  } else {
    // Show "Request Phone Number" button
    showRequestButton();
  }
};
```

#### 3. Display Notification with Phone Number
```javascript
const displayPhoneNotification = (notification) => {
  if (notification.title === 'Phone Number Approved') {
    // Extract phone number from content using regex
    const phoneMatch = notification.content.match(/Phone: (.+)$/);
    const phoneNumber = phoneMatch ? phoneMatch[1] : null;
    
    // Show notification
    showNotification({
      title: notification.title,
      message: notification.content,
      phoneNumber: phoneNumber,  // ✅ Extracted from content
    });
    
    // Parse content to get store name
    const storeNameMatch = notification.content.match(/^(.+?) has approved/);
    const storeName = storeNameMatch ? storeNameMatch[1] : 'Store';
  }
};
```

#### 4. Get All Revealed Phone Numbers
```javascript
const getRevealedPhones = async () => {
  const response = await fetch('/api/buyer/phone-request/revealed', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  const data = await response.json();
  displayPhoneNumbersList(data.data.phone_numbers);
};
```

---

### Seller Side

#### 1. Listen for Phone Request Notifications
```javascript
const listenForPhoneRequests = () => {
  // When new notification arrives
  socket.on('notification', (notification) => {
    if (notification.title === 'Phone Number Request') {
      // Extract request ID from content
      const requestIdMatch = notification.content.match(/Request ID: (\d+)$/);
      const revealPhoneId = requestIdMatch ? requestIdMatch[1] : null;
      
      // Extract buyer name and store name from content
      const buyerMatch = notification.content.match(/^(.+?) has requested/);
      const buyerName = buyerMatch ? buyerMatch[1] : 'A buyer';
      
      // Show notification with approve/decline buttons
      showPhoneRequestNotification({
        title: notification.title,
        message: notification.content,
        buyerName: buyerName,
        revealPhoneId: revealPhoneId
      });
    }
  });
};
```

#### 2. Approve Request
```javascript
const approvePhoneRequest = async (revealPhoneId) => {
  const response = await fetch(`/api/seller/phone-requests/${revealPhoneId}/approve`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  const data = await response.json();
  
  if (data.status === 'success') {
    showSuccessToast('Phone number shared!');
    // Remove from pending list
    removeRequestFromList(revealPhoneId);
  }
};
```

#### 3. Decline Request
```javascript
const declinePhoneRequest = async (revealPhoneId) => {
  const response = await fetch(`/api/seller/phone-requests/${revealPhoneId}/decline`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  const data = await response.json();
  
  if (data.status === 'success') {
    showSuccessToast('Request declined');
    removeRequestFromList(revealPhoneId);
  }
};
```

---

## 🧪 Testing Examples

### Using cURL

**1. Buyer Requests Phone:**
```bash
curl -X POST https://api.example.com/api/buyer/phone-request \
  -H "Authorization: Bearer {buyer_token}" \
  -H "Content-Type: application/json" \
  -d '{"store_id": 123}'
```

**2. Check Request Status:**
```bash
curl -X GET "https://api.example.com/api/buyer/phone-request/status?store_id=123" \
  -H "Authorization: Bearer {buyer_token}"
```

**3. Get Revealed Phone Numbers:**
```bash
curl -X GET https://api.example.com/api/buyer/phone-request/revealed \
  -H "Authorization: Bearer {buyer_token}"
```

**4. Seller Gets Pending Requests:**
```bash
curl -X GET https://api.example.com/api/seller/phone-requests \
  -H "Authorization: Bearer {seller_token}"
```

**5. Seller Approves Request:**
```bash
curl -X POST https://api.example.com/api/seller/phone-requests/12/approve \
  -H "Authorization: Bearer {seller_token}"
```

**6. Seller Declines Request:**
```bash
curl -X POST https://api.example.com/api/seller/phone-requests/12/decline \
  -H "Authorization: Bearer {seller_token}"
```

---

## ✅ Implementation Checklist

- [x] Removed `chat_id` dependency from `RevealPhone` model
- [x] Updated migration to remove `chat_id` field
- [x] Added unique constraint on `user_id` + `store_id`
- [x] Refactored buyer controller to use notifications
- [x] Refactored seller controller to use notifications
- [x] Phone number embedded in approval notification
- [x] Added route for getting revealed phone numbers
- [x] Added authorization checks
- [x] Added null safety checks
- [x] Updated API documentation

---

## 🚀 Key Features

✅ **No Chat Dependency**: Works independently of chat system
✅ **Notification-Based**: All communication through notifications
✅ **Phone Number Embedded**: Phone number included in approval notification
✅ **Duplicate Prevention**: Unique constraint prevents multiple requests
✅ **Status Tracking**: Track request status (pending/revealed)
✅ **Authorization**: Sellers can only manage their own store requests
✅ **Clean Decline**: Request is deleted when declined
✅ **Phone Number History**: Buyers can access all revealed phone numbers

---

## 📱 Notification Flow Diagram

```
BUYER                    SYSTEM                    SELLER
  |                         |                         |
  |  Request Phone          |                         |
  |------------------------>|                         |
  |                         |  Create RevealPhone     |
  |                         |  (is_revealed = false)  |
  |                         |                         |
  |                         |  Send Notification      |
  |                         |------------------------>|
  |                         |  "John Doe has          |
  |                         |   requested phone"      |
  |                         |                         |
  |                         |         <---------------|
  |                         |         Approve/Decline |
  |                         |                         |
  |  Receive Notification   |                         |
  |<------------------------|                         |
  |  "Phone approved:       |                         |
  |   +1234567890"          |                         |
  |                         |                         |
```

The complete notification-based phone request system is now ready for production use! 🎯
