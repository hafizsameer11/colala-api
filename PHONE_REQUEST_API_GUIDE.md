# 📞 Phone Number Request API Guide

## Overview

This system allows buyers to request phone numbers from sellers through the chat system. The seller can then approve or decline the request, and if approved, the phone number is shared via chat message.

---

## 🔄 Complete Flow

### Step 1: Buyer Requests Phone Number
1. Buyer clicks "Request Phone Number" button for a store
2. System checks if chat exists between buyer and store
3. If no chat exists, creates a new chat
4. Creates a `RevealPhone` record with `is_revealed = false`
5. Sends request message to seller in the chat
6. Shows notification to seller

### Step 2: Seller Reviews Request
1. Seller sees pending phone requests in their dashboard
2. Seller can view buyer information
3. Seller can either:
   - **Approve**: Phone number is shared in chat
   - **Decline**: Request is deleted, buyer notified

### Step 3: Phone Number Shared (If Approved)
1. System updates `RevealPhone` to `is_revealed = true`
2. Sends message to buyer with phone number
3. Buyer can now see the phone number in chat

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
    "chat_id": 45,
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
    "chat_id": 45,
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
    "chat_id": 45,
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
    "is_revealed": false,
    "chat_id": 45
  }
}
```

**Request Pending:**
```json
{
  "status": "success",
  "data": {
    "has_request": true,
    "is_revealed": false,
    "chat_id": 45
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
    "chat_id": 45,
    "phone_number": "+1234567890"
  }
}
```

---

### **SELLER ENDPOINTS**

#### 3. Get Pending Phone Requests
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
        "chat_id": 45,
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

#### 4. Approve Phone Request
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
    "chat_id": 45,
    "phone_number": "+1234567890",
    "is_revealed": true
  }
}
```

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

**Already Approved (200):**
```json
{
  "status": "success",
  "message": "Phone number already shared"
}
```

---

#### 5. Decline Phone Request
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
    "chat_id": 45,
    "is_revealed": false
  }
}
```

---

## 📊 Database Structure

### `reveal_phones` Table
```sql
CREATE TABLE reveal_phones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    is_revealed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);
```

---

## 💬 Chat Message Types

### 1. Request Message (Buyer to Seller)
```json
{
  "sender_type": "user",
  "message": "I would like to request your phone number to discuss further."
}
```

### 2. Request Notification (System to Seller)
```json
{
  "sender_type": "store",
  "message": "📞 John Doe has requested your phone number. [APPROVE] or [DECLINE]",
  "meta": {
    "type": "phone_request",
    "reveal_phone_id": 12,
    "status": "pending"
  }
}
```

### 3. Approved Message (Seller to Buyer)
```json
{
  "sender_type": "store",
  "message": "✅ Phone number approved! You can reach us at: +1234567890",
  "meta": {
    "type": "phone_approved",
    "phone_number": "+1234567890",
    "reveal_phone_id": 12
  }
}
```

### 4. Declined Message (Seller to Buyer)
```json
{
  "sender_type": "store",
  "message": "❌ Sorry, we cannot share our phone number at this time. Please continue chatting here.",
  "meta": {
    "type": "phone_declined",
    "reveal_phone_id": 12
  }
}
```

---

## 🔒 Security & Validation

### Authorization Rules
1. **Buyer Requests:**
   - Must be authenticated
   - Can only request phone for stores they can chat with
   - Cannot spam requests (checked before creating)

2. **Seller Actions:**
   - Must be authenticated
   - Can only approve/decline requests for their own stores
   - Cannot approve already approved requests

### Data Validation
- `store_id` must exist in database
- `reveal_phone_id` must be valid and belong to seller's store
- Chat must exist before phone request can be processed

---

## 🎨 Frontend Implementation Guide

### Buyer Side

#### 1. Display "Request Phone Number" Button
```javascript
// Check if phone number is already revealed
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

#### 2. Request Phone Number
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
    // Navigate to chat
    navigateToChat(data.data.chat_id);
  }
};
```

---

### Seller Side

#### 1. Display Pending Requests
```javascript
const fetchPendingRequests = async () => {
  const response = await fetch('/api/seller/phone-requests', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  const data = await response.json();
  
  // Display requests in UI
  displayRequests(data.data.requests);
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
    // Show success message
    showSuccessToast('Phone number shared!');
    // Navigate to chat
    navigateToChat(data.data.chat_id);
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
    // Show success message
    showSuccessToast('Request declined');
    // Remove from pending list
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

**3. Seller Gets Pending Requests:**
```bash
curl -X GET https://api.example.com/api/seller/phone-requests \
  -H "Authorization: Bearer {seller_token}"
```

**4. Seller Approves Request:**
```bash
curl -X POST https://api.example.com/api/seller/phone-requests/12/approve \
  -H "Authorization: Bearer {seller_token}"
```

**5. Seller Declines Request:**
```bash
curl -X POST https://api.example.com/api/seller/phone-requests/12/decline \
  -H "Authorization: Bearer {seller_token}"
```

---

## ✅ Implementation Checklist

- [x] Created `RevealPhone` model
- [x] Created `reveal_phones` migration
- [x] Created `PhoneRequestController` for buyers
- [x] Created `SellerPhoneRequestController` for sellers
- [x] Added buyer routes to `api.php`
- [x] Added seller routes to `seller.php`
- [x] Implemented request creation
- [x] Implemented approval flow
- [x] Implemented decline flow
- [x] Added chat message integration
- [x] Added status checking
- [x] Added authorization checks
- [x] Added null safety checks

---

## 🚀 Features

✅ **Automatic Chat Creation**: Creates chat if none exists
✅ **Duplicate Prevention**: Prevents multiple requests for same store
✅ **Status Tracking**: Track request status (pending/revealed)
✅ **Chat Integration**: All communication through existing chat system
✅ **Authorization**: Sellers can only manage their own store requests
✅ **Notifications**: Both parties get notified via chat messages
✅ **Clean Decline**: Request is deleted when declined
✅ **Phone Number Protection**: Only shared after explicit approval

The complete phone request system is now ready for production use! 🎯

