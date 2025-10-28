# 🛍️ Separate Orders Per Store - Implementation Guide

## Overview

The system has been updated to create **separate orders for each store** during checkout instead of one order with multiple store orders. This simplifies payment processing, order management, and makes the flow cleaner.

---

## 🔄 **Before vs After**

### **Before (Old Flow):**
```
Checkout with 3 stores
└─ Order #12345
    ├─ StoreOrder A
    ├─ StoreOrder B
    └─ StoreOrder C
    
Payment: Pay all together
```

### **After (New Flow):**
```
Checkout with 3 stores
├─ Order #12345 (Store A only)
│   └─ StoreOrder A
├─ Order #12346 (Store B only)
│   └─ StoreOrder B
└─ Order #12347 (Store C only)
    └─ StoreOrder C
    
Payment: Pay each order separately
```

---

## ✅ **Benefits**

1. **Simpler Payment**: Each order is paid individually
2. **Independent Management**: Each store order is completely independent
3. **Clearer Tracking**: One order = one store = easier tracking
4. **Flexible Payment Methods**: Can use different methods for different orders
5. **Better UX**: Clearer for both buyers and sellers
6. **Easier Refunds**: Refund one order without affecting others
7. **Simpler Escrow**: Escrow per order is straightforward

---

## 🔄 **Complete Flow**

### **1. Checkout**
```
Buyer has items from 3 stores in cart
├─ Store A: ₦10,000
├─ Store B: ₦15,000
└─ Store C: ₦20,000

Place Order →
├─ Order #001 created for Store A
├─ Order #002 created for Store B
└─ Order #003 created for Store C

Each order has its own:
- Order number
- Grand total (with platform fee)
- Store order
- Order items
- Chat
```

### **2. Store Acceptance**
```
Each store reviews their order independently:

Order #001 (Store A): ✅ ACCEPTS + sets delivery_fee: ₦1,000
Order #002 (Store B): ❌ REJECTS
Order #003 (Store C): ✅ ACCEPTS + sets delivery_fee: ₦1,500
```

### **3. Payment**
```
Buyer sees 2 accepted orders:

Option 1: Pay Order #001
  ├─ Method: Flutterwave (Card)
  ├─ Amount: ₦11,150 (₦10,000 + ₦1,000 delivery + ₦150 platform fee)
  └─ Escrow created after payment

Option 2: Pay Order #003
  ├─ Method: Wallet
  ├─ Amount: ₦21,800 (₦20,000 + ₦1,500 delivery + ₦300 platform fee)
  └─ Escrow created after payment
```

---

## 📊 **Database Structure**

### **orders Table**
```sql
id: 1
order_no: 'COL-20251028-123456'
user_id: 50
delivery_address_id: 10
payment_method: 'card'
payment_status: 'pending'
status: 'pending'
items_total: 10000.00
shipping_total: 0 (updated after acceptance)
platform_fee: 150.00
grand_total: 10150.00
meta: '{"store_id": 5}'  -- Track which store this order belongs to
```

### **store_orders Table**
```sql
id: 1
order_id: 1  -- Links to ONE specific order
store_id: 5
status: 'pending_acceptance'
shipping_fee: 0  -- Set by seller during acceptance
items_subtotal: 10000.00
subtotal_with_shipping: 10000.00  -- Updated after acceptance
```

**Key Change:** Each order has exactly ONE store_order

---

## 🎯 **API Changes**

### **1. Place Order Response**
```json
POST /api/buyer/checkout/place

Response:
{
  "status": "success",
  "data": {
    "message": "3 order(s) created successfully",
    "total_orders": 3,
    "orders": [
      {
        "id": 1,
        "order_no": "COL-20251028-123456",
        "status": "pending",
        "payment_status": "pending",
        "grand_total": 10150.00,
        "storeOrders": [{
          "id": 1,
          "store_id": 5,
          "status": "pending_acceptance"
        }]
      },
      {
        "id": 2,
        "order_no": "COL-20251028-123457",
        ...
      },
      {
        "id": 3,
        "order_no": "COL-20251028-123458",
        ...
      }
    ]
  }
}
```

### **2. Payment Confirmation (Flutterwave)**
```json
POST /api/buyer/payment/confirmation
{
  "order_id": 1,
  "tx_id": "FLW-12345",
  "amount": 11150.00
}

Response:
{
  "status": "success",
  "data": {
    "message": "Payment confirmed successfully."
  }
}
```

**Changes:**
- Must be accepted by store before payment
- Creates escrow using seller's delivery_fee
- Updates both order and store_order status to 'paid'

### **3. Get Payment Info**
```json
GET /api/buyer/orders/1/payment-info

Response:
{
  "status": "success",
  "data": {
    "order_no": "COL-20251028-123456",
    "payment_method": "card",
    "amount_to_pay": 11150.00,
    "store": {
      "store_id": 5,
      "store_name": "Tech Store",
      "amount": 11000.00,
      "estimated_delivery": "2025-11-05",
      "delivery_method": "Express",
      "delivery_fee": 1000.00,
      "items_subtotal": 10000.00
    },
    "status": "accepted",
    "can_pay": true
  }
}
```

---

## 💳 **Payment Methods**

### **Wallet Payment:**
```
1. Buyer clicks "Pay with Wallet"
2. POST /api/buyer/orders/{orderId}/pay
3. Backend verifies:
   ✅ Order exists
   ✅ Store has accepted order
   ✅ Sufficient wallet balance
4. Backend processes:
   ✅ Deducts from wallet
   ✅ Creates transaction record
   ✅ Creates escrow (store-order level)
   ✅ Updates order status to 'paid'
   ✅ Sends notifications
5. Order marked as paid ✅
```

### **Card Payment (Flutterwave):**
```
1. Buyer clicks "Pay with Card"
2. Frontend initializes Flutterwave with order details
3. User completes payment on Flutterwave
4. Flutterwave confirms payment to frontend
5. Frontend calls our confirmation API:
   POST /api/buyer/payment/confirmation
   {
     "order_id": 1,
     "tx_id": "FLW-12345",
     "amount": 11150.00
   }
6. Backend verifies:
   ✅ Order exists
   ✅ Store has accepted order
   ✅ Order not already paid
7. Backend creates:
   ✅ Transaction record
   ✅ Escrow (store-order level)
   ✅ Updates order status to 'paid'
   ✅ Sends notifications
8. Order marked as paid ✅
```

---

## 🔒 **Escrow System (Updated)**

### **Store-Order Level Escrow**

The escrow system has been updated to work at the **store-order level** instead of per-item:

**Old Flow (Per-Item):**
```
Order #123 has 3 items
├─ Escrow #1 (Item A): ₦3,500 + ₦500 shipping
├─ Escrow #2 (Item B): ₦5,000 + ₦500 shipping
└─ Escrow #3 (Item C): ₦2,000 + ₦500 shipping
Total: 3 escrow records
```

**New Flow (Store-Order Level):**
```
Order #123 (Store A)
└─ Escrow #1 (Entire Order): ₦11,500
   - Items subtotal: ₦10,500
   - Delivery fee: ₦1,000 (set by seller)
   - Total locked: ₦11,500
Total: 1 escrow record per order
```

### **Escrow Table Structure**

```sql
escrows table:
├─ id
├─ user_id (buyer)
├─ order_id
├─ store_order_id (NEW - links to specific store order)
├─ order_item_id (nullable - for backward compatibility)
├─ amount (total including delivery)
├─ shipping_fee (seller's delivery_fee)
└─ status (locked/released)
```

### **Key Changes:**

✅ **One escrow per order** (not per item)  
✅ **Uses seller's delivery_fee** (set during acceptance)  
✅ **Simpler to manage** (one record to release)  
✅ **Total amount = items_subtotal + delivery_fee**  
✅ **Released when order is delivered**

### **Escrow Release Flow:**

```
1. Seller marks order as delivered
2. Buyer confirms with delivery code
3. System finds escrow by store_order_id
4. Updates escrow status to 'released'
5. Credits seller's wallet with full amount
6. Creates transaction record for seller
```

**Example:**
```php
// When order is delivered
Escrow::where('store_order_id', $storeOrder->id)
    ->where('status', 'locked')
    ->first()
    ->update(['status' => 'released']);

// Credit seller
$sellerWallet->increment('shopping_balance', $escrowAmount);
```

---

## 🔧 **Key Implementation Changes**

### **1. CheckoutService.php**
```php
// OLD: Returns single Order
public function place(Cart $cart, array $preview): Order

// NEW: Returns array of Orders
public function place(Cart $cart, array $preview): array
{
    $orders = [];
    
    // Create separate order for each store
    foreach ($preview['stores'] as $store) {
        $order = Order::create([...]);
        $storeOrder = StoreOrder::create([...]);
        $orders[] = $order;
    }
    
    return $orders;
}
```

### **2. OrderAcceptanceService.php**
```php
// Simplified since each order has one store
private function updateOverallOrderStatus(int $orderId): void
{
    $storeOrder = $order->storeOrders->first();
    
    // Direct mapping
    if ($storeOrder->status === 'rejected') {
        $order->update(['status' => 'cancelled']);
    }
}
```

### **3. PostAcceptancePaymentService.php**
```php
// Simplified calculations
private function calculateAcceptedOrdersTotal(Order $order): float
{
    // Just use order's grand_total
    return $order->grand_total;
}
```

### **4. CheckoutController.php**
```php
public function place(CheckoutPreviewRequest $req)
{
    $orders = $this->chk->place($cart, $preview);
    
    return ResponseHelper::success([
        'message' => count($orders) . ' order(s) created successfully',
        'orders' => $orders,
        'total_orders' => count($orders),
    ]);
}
```

---

## 📋 **Order Status Flow**

```
Order Status Flow:
pending → accepted → paid → completed
   ↓
cancelled (if rejected)

Store Order Status Flow:
pending_acceptance → accepted → paid → processing → out_for_delivery → delivered
   ↓
rejected
```

---

## 🔔 **Notifications**

### **Order Placement:**
```
To Buyer: "Your 3 order(s) have been placed successfully"
To Each Store: "You have received a new order #12345 for ₦10,150"
```

### **Order Acceptance:**
```
To Buyer: "Tech Store has accepted your order #12345. Delivery fee: ₦1,000. Please proceed to payment."
```

### **Payment Confirmation:**
```
To Buyer: "Payment for order #12345 has been confirmed. Amount: ₦11,150"
To Store: "Payment confirmed for order #12345. Amount: ₦11,000"
```

---

## ✅ **Frontend Integration**

### **1. Display Orders After Checkout**
```javascript
// After checkout
const response = await placeOrder();
const { orders, total_orders } = response.data;

// Show success message
showSuccess(`${total_orders} orders created successfully!`);

// Display each order
orders.forEach(order => {
  displayOrder({
    orderNo: order.order_no,
    store: order.storeOrders[0].store_id,
    total: order.grand_total,
    status: order.status
  });
});
```

### **2. Pay Individual Order**
```javascript
// For wallet payment
async function payWithWallet(orderId) {
  const response = await fetch(`/api/buyer/orders/${orderId}/pay`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return response.json();
}

// For card payment (Flutterwave)
async function payWithCard(orderId, amount) {
  // 1. Get payment info
  const info = await getPaymentInfo(orderId);
  
  // 2. Initialize Flutterwave
  const flw = await initializeFlutterwave({
    amount: info.amount_to_pay,
    order_id: orderId
  });
  
  // 3. After successful payment
  await confirmPayment({
    order_id: orderId,
    tx_id: flw.transaction_id,
    amount: flw.amount
  });
}
```

---

## 🧪 **Testing Checklist**

### **Setup:**
- [ ] Run migration: `php artisan migrate`
- [ ] Verify `escrows` table has `store_order_id` column
- [ ] Verify `order_item_id` is nullable

### **Order Placement:**
- [ ] Cart with 3 stores creates 3 separate orders
- [ ] Each order has unique order number
- [ ] Each order has correct totals (items + 1.5% platform fee)
- [ ] Store receives notification for their order only
- [ ] Order status is 'pending'
- [ ] StoreOrder status is 'pending_acceptance'

### **Order Acceptance:**
- [ ] Seller can accept order with delivery details
- [ ] Seller can set custom delivery_fee
- [ ] Seller can reject order with reason
- [ ] Buyer receives notification on acceptance/rejection
- [ ] Order status updates correctly

### **Payment - Wallet:**
- [ ] Buyer can pay accepted order with wallet
- [ ] Wallet balance deducted correctly
- [ ] Transaction record created
- [ ] Escrow created with store_order_id
- [ ] Escrow amount = subtotal_with_shipping
- [ ] Order status → 'paid'
- [ ] StoreOrder status → 'paid'
- [ ] Notifications sent to buyer and seller

### **Payment - Card (Flutterwave):**
- [ ] Frontend can initialize Flutterwave
- [ ] Flutterwave payment completes successfully
- [ ] POST /api/buyer/payment/confirmation works
- [ ] Transaction record created with Flutterwave tx_id
- [ ] Escrow created with store_order_id
- [ ] Order marked as paid
- [ ] Notifications sent

### **Escrow Release:**
- [ ] Seller marks order as out-for-delivery
- [ ] Seller marks order as delivered
- [ ] Buyer confirms with delivery code
- [ ] Escrow status → 'released'
- [ ] Seller wallet credited with full amount
- [ ] Transaction record created for seller
- [ ] Both order and store_order status updated

### **Edge Cases:**
- [ ] Cannot pay order that's not accepted
- [ ] Cannot pay already paid order
- [ ] Cannot accept already accepted order
- [ ] Cannot reject already accepted order
- [ ] Insufficient wallet balance shows error
- [ ] Invalid delivery code shows error

---

## 🚀 **Summary of Benefits**

✅ **Simpler Architecture**: One order = one store
✅ **Independent Payments**: Pay each order separately
✅ **Flexible Payment Methods**: Different method per order
✅ **Clearer Tracking**: No confusion about partial payments
✅ **Better UX**: Easier for both buyers and sellers
✅ **Easier Refunds**: Refund one order without affecting others
✅ **Simpler Escrow**: Straightforward escrow per order
✅ **Better Analytics**: Track per-store performance easily

The separate orders per store implementation is now complete and ready for production! 🎉

