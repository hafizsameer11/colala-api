# Push Notifications Implementation Guide

## Overview
Push notifications have been implemented throughout the application using Expo Push Notification Service. All notifications are sent both as in-app notifications (stored in database) and push notifications (sent to user's device via Expo).

## Implementation Details

### 1. Enhanced UserNotificationHelper
**File:** `app/Helpers/UserNotificationHelper.php`

The helper now automatically sends push notifications via Expo when creating in-app notifications:
- Checks if user has `expo_push_token`
- Sends push notification with structured data payload
- Handles errors gracefully (logs but doesn't fail notification creation)

**Methods:**
- `notify($user_id, $title, $content, $data = [])` - Creates in-app notification + sends push notification
- `pushOnly($user_id, $title, $body, $data = [])` - Sends push notification only (without in-app notification)

---

## Notification Events Implemented

### 1. Authentication Events

#### Login Notification
**Location:** `app/Http/Controllers/Api/AuthController.php`
- **Trigger:** User successfully logs in
- **Recipient:** Logged-in user
- **Data Payload:**
  ```json
  {
    "type": "login",
    "timestamp": "2025-11-21T14:30:00Z"
  }
  ```

---

### 2. Order Events

#### New Order Received (Seller)
**Location:** `app/Services/Buyer/CheckoutService.php`
- **Trigger:** Buyer places an order
- **Recipient:** Store owner
- **Data Payload:**
  ```json
  {
    "type": "new_order",
    "order_id": 123,
    "order_no": "COL-20251121-123456",
    "store_order_id": 456,
    "amount": 5000.00
  }
  ```

#### Orders Placed Successfully (Buyer)
**Location:** `app/Services/Buyer/CheckoutService.php`
- **Trigger:** Buyer successfully places order(s)
- **Recipient:** Buyer
- **Data Payload:**
  ```json
  {
    "type": "order_placed",
    "order_count": 2,
    "total_amount": 10000.00
  }
  ```

#### Order Accepted - Payment Required (Buyer)
**Location:** `app/Services/Seller/OrderAcceptanceService.php`
- **Trigger:** Seller accepts order and sets delivery fee
- **Recipient:** Buyer
- **Data Payload:**
  ```json
  {
    "type": "order_accepted",
    "order_id": 123,
    "order_no": "COL-20251121-123456",
    "store_order_id": 456,
    "store_id": 789,
    "delivery_fee": 500.00,
    "total_amount": 5500.00
  }
  ```

#### Order Rejected (Buyer)
**Location:** `app/Services/Seller/OrderAcceptanceService.php`
- **Trigger:** Seller rejects order
- **Recipient:** Buyer
- **Data Payload:**
  ```json
  {
    "type": "order_rejected",
    "order_id": 123,
    "order_no": "COL-20251121-123456",
    "store_order_id": 456,
    "store_id": 789,
    "reason": "Out of stock"
  }
  ```

#### Payment Confirmed (Buyer)
**Location:** `app/Http/Controllers/Api/Buyer/CheckoutController.php`
- **Trigger:** Payment is confirmed for an order
- **Recipient:** Buyer
- **Data Payload:**
  ```json
  {
    "type": "payment_confirmed",
    "order_id": 123,
    "order_no": "COL-20251121-123456",
    "amount": 5500.00
  }
  ```

#### Payment Received (Seller)
**Location:** `app/Http/Controllers/Api/Buyer/CheckoutController.php`
- **Trigger:** Payment is confirmed for an order
- **Recipient:** Store owner
- **Data Payload:**
  ```json
  {
    "type": "payment_received",
    "order_id": 123,
    "order_no": "COL-20251121-123456",
    "store_order_id": 456,
    "amount": 5500.00
  }
  ```

#### Order Status Update
**Location:** `app/Http/Controllers/Api/Admin/AdminOrderManagementController.php`
- **Trigger:** Order status is updated (processing, shipped, delivered, etc.)
- **Recipients:** Both buyer and seller
- **Data Payload:**
  ```json
  {
    "type": "order_status_update",
    "order_id": 123,
    "order_no": "COL-20251121-123456",
    "store_order_id": 456,
    "status": "shipped",
    "notes": "Order shipped via DHL"
  }
  ```

---

### 3. Chat/Message Events

#### New Message from Customer (Seller)
**Location:** `app/Services/Buyer/ChatService.php`
- **Trigger:** Buyer sends a message in chat
- **Recipient:** Store owner
- **Data Payload:**
  ```json
  {
    "type": "chat_message",
    "chat_id": 123,
    "store_id": 456,
    "sender_type": "buyer",
    "sender_id": 789,
    "order_id": 101
  }
  ```

#### New Message from Store (Buyer)
**Location:** `app/Services/Buyer/ChatService.php` & `app/Services/SellerChatService.php`
- **Trigger:** Seller sends a message in chat
- **Recipient:** Buyer
- **Data Payload:**
  ```json
  {
    "type": "chat_message",
    "chat_id": 123,
    "store_id": 456,
    "sender_type": "store",
    "order_id": 101
  }
  ```

---

### 4. Product Events

#### Product Created Successfully
**Location:** `app/Services/ProductService.php`
- **Trigger:** Seller creates a new product
- **Recipient:** Seller
- **Data Payload:**
  ```json
  {
    "type": "product_created",
    "product_id": 123,
    "product_name": "Product Name",
    "store_id": 456
  }
  ```

---

### 5. Withdrawal Events

#### Withdrawal Request Submitted
**Location:** `app/Http/Controllers/Api/WalletWithdrawalController.php`
- **Trigger:** User submits manual withdrawal request
- **Recipient:** User
- **Data Payload:**
  ```json
  {
    "type": "withdrawal_requested",
    "withdrawal_id": 123,
    "amount": 5000.00,
    "status": "pending"
  }
  ```

#### Withdrawal Initiated (Automatic)
**Location:** `app/Http/Controllers/Api/WalletWithdrawalController.php`
- **Trigger:** Automatic withdrawal via Flutterwave is initiated
- **Recipient:** User
- **Data Payload:**
  ```json
  {
    "type": "withdrawal_initiated",
    "withdrawal_id": 123,
    "amount": 5000.00,
    "reference": "payout-550e8400-e29b-41d4-a716-446655440000",
    "status": "pending"
  }
  ```

#### Withdrawal Status Update
**Location:** `app/Http/Controllers/WebhookController.php`
- **Trigger:** Flutterwave webhook updates withdrawal status
- **Recipient:** User
- **Data Payload:**
  ```json
  {
    "type": "withdrawal_status_update",
    "withdrawal_id": 123,
    "amount": 5000.00,
    "status": "approved",
    "reference": "payout-550e8400-e29b-41d4-a716-446655440000",
    "remarks": "Transfer completed successfully"
  }
  ```

---

### 6. Dispute Events

#### Dispute Created (Buyer & Seller)
**Location:** `app/Http/Controllers/Buyer/DisputeController.php`
- **Trigger:** Buyer creates a dispute
- **Recipients:** Both buyer and seller
- **Data Payload:**
  ```json
  {
    "type": "dispute_created",
    "dispute_id": 123,
    "store_order_id": 456,
    "category": "Late Delivery",
    "buyer_id": 789
  }
  ```

#### Dispute Status Updated
**Location:** `app/Http/Controllers/Api/Admin/AdminDisputeController.php`
- **Trigger:** Admin updates dispute status
- **Recipients:** Both buyer and seller
- **Data Payload:**
  ```json
  {
    "type": "dispute_status_update",
    "dispute_id": 123,
    "status": "resolved",
    "won_by": "buyer"
  }
  ```

---

## Frontend Integration

### Setting Expo Push Token
Users need to save their Expo push token when they log in or open the app:

**Endpoint:** `POST /api/push-notification/save-expo-push-token`
**Request:**
```json
{
  "expoPushToken": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"
}
```

### Handling Push Notifications

When a push notification is received, the app should:
1. Display the notification to the user
2. Extract the `type` and related IDs from the `data` payload
3. Navigate to the appropriate screen based on the notification type

**Example Notification Types & Navigation:**
- `new_order` → Navigate to order details
- `order_status_update` → Navigate to order tracking
- `chat_message` → Navigate to chat screen
- `payment_confirmed` → Navigate to order details
- `withdrawal_status_update` → Navigate to wallet/withdrawal history
- `dispute_created` → Navigate to dispute details

---

## Testing

### Test Notification Endpoint
**Endpoint:** `GET /api/push-notification/test/{userId}`
**Description:** Sends a test push notification to the specified user

---

## Configuration

### Environment Variables
Ensure these are set in `.env`:
- No additional environment variables needed (Expo Push API is public)

### User Model
Users must have the `expo_push_token` field populated:
- Field: `expo_push_token` (string, nullable)
- Set via: `POST /api/push-notification/save-expo-push-token`

---

## Error Handling

- Push notification failures are logged but don't prevent in-app notifications from being created
- Errors are logged to Laravel log with context (user_id, title, error message)
- The system gracefully handles missing `expo_push_token` (skips push, creates in-app notification only)

---

## Notification Data Structure

All push notifications include:
- **Title:** Short notification title
- **Body:** Notification message/content
- **Data:** Structured JSON object with:
  - `type`: Notification type identifier
  - Related IDs (order_id, chat_id, etc.)
  - Additional context (amount, status, etc.)

---

## Summary

✅ **Implemented Events:**
- Login
- Order creation, acceptance, rejection, payment, status updates
- Chat messages (buyer ↔ seller)
- Product creation
- Withdrawal requests and status updates
- Dispute creation and status updates

✅ **Features:**
- Automatic push notifications for all in-app notifications
- Structured data payloads for easy frontend handling
- Error handling and logging
- Support for both in-app and push notifications

---

## Next Steps (Optional Enhancements)

1. **Review Notifications:** Add notifications when products/stores receive reviews
2. **Post Notifications:** Add notifications for post likes, comments, shares
3. **Follow Notifications:** Add notifications when users follow stores
4. **Promotion Notifications:** Add notifications for sales, discounts, coupons
5. **Admin Notifications:** Add notifications for admin actions (approvals, rejections)

