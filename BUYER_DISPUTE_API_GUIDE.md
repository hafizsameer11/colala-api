# Buyer Dispute API Integration Guide

## Base URL
```
/api
```

## Authentication
All endpoints require authentication. Include the bearer token in the Authorization header:
```
Authorization: Bearer {your_token}
```

---

## 1. Create Dispute

Creates a new dispute with a dispute chat. This endpoint automatically creates a separate dispute chat where buyer, seller, and admin can communicate.

**Endpoint:** `POST /api/dispute`

**Content-Type:** `multipart/form-data` (for file uploads)

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `chat_id` | integer | Yes | ID of the original chat conversation |
| `store_order_id` | integer | Yes | ID of the store order related to the dispute |
| `category` | string | Yes | Dispute category (e.g., "Order Dispute", "Late Delivery", "Wrong Item", "Damaged Product") |
| `details` | string | No | Detailed description of the dispute |
| `images[]` | file[] | No | Array of image files (jpg, jpeg, png, max 5MB each) |

### Request Example

```bash
curl -X POST "https://your-api.com/api/dispute" \
  -H "Authorization: Bearer {token}" \
  -F "chat_id=123" \
  -F "store_order_id=456" \
  -F "category=Order Dispute" \
  -F "details=The product I received is different from what I ordered" \
  -F "images[]=@/path/to/image1.jpg" \
  -F "images[]=@/path/to/image2.jpg"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "dispute": {
      "id": 1,
      "chat_id": 123,
      "dispute_chat_id": 1,
      "store_order_id": 456,
      "user_id": 10,
      "category": "Order Dispute",
      "details": "The product I received is different from what I ordered",
      "images": [
        "disputes/image1.jpg",
        "disputes/image2.jpg"
      ],
      "status": "open",
      "won_by": null,
      "resolution_notes": null,
      "created_at": "2025-11-12T15:00:00.000000Z",
      "updated_at": "2025-11-12T15:00:00.000000Z",
      "resolved_at": null,
      "closed_at": null,
      "dispute_chat": {
        "id": 1,
        "dispute_id": 1,
        "buyer_id": 10,
        "seller_id": 20,
        "store_id": 5,
        "created_at": "2025-11-12T15:00:00.000000Z",
        "updated_at": "2025-11-12T15:00:00.000000Z",
        "buyer": {
          "id": 10,
          "full_name": "John Doe",
          "email": "john@example.com"
        },
        "seller": {
          "id": 20,
          "full_name": "Jane Seller",
          "email": "jane@example.com"
        },
        "store": {
          "id": 5,
          "store_name": "Awesome Store"
        }
      },
      "store_order": {
        "id": 456,
        "order_id": 789,
        "status": "delivered",
        "items_subtotal": "100.00",
        "shipping_fee": "10.00",
        "subtotal_with_shipping": "110.00"
      }
    },
    "dispute_chat": {
      "id": 1,
      "dispute_id": 1,
      "buyer_id": 10,
      "seller_id": 20,
      "store_id": 5,
      "created_at": "2025-11-12T15:00:00.000000Z",
      "updated_at": "2025-11-12T15:00:00.000000Z"
    }
  },
  "message": "Dispute created successfully."
}
```

### Error Response (422)

```json
{
  "status": "error",
  "data": {
    "store_order_id": ["The store order id field is required."],
    "category": ["The category field is required."]
  },
  "message": "The store order id field is required."
}
```

---

## 2. List My Disputes

Retrieves all disputes created by the authenticated buyer.

**Endpoint:** `GET /api/dispute`

### Request Example

```bash
curl -X GET "https://your-api.com/api/dispute" \
  -H "Authorization: Bearer {token}"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "category": "Order Dispute",
      "details": "The product I received is different from what I ordered",
      "images": [
        "disputes/image1.jpg",
        "disputes/image2.jpg"
      ],
      "status": "open",
      "won_by": null,
      "resolution_notes": null,
      "created_at": "2025-11-12T15:00:00.000000Z",
      "store_order": {
        "id": 456,
        "status": "delivered"
      },
      "store": {
        "id": 5,
        "name": "Awesome Store"
      },
      "last_message": {
        "message": "📌 Dispute created: Order Dispute\n\nThe product I received is different from what I ordered",
        "sender_type": "buyer",
        "created_at": "2025-11-12T15:00:00.000000Z"
      }
    },
    {
      "id": 2,
      "category": "Late Delivery",
      "details": "Order was supposed to arrive 3 days ago",
      "images": [],
      "status": "resolved",
      "won_by": "buyer",
      "resolution_notes": "Refund issued to buyer",
      "created_at": "2025-11-10T10:00:00.000000Z",
      "store_order": {
        "id": 455,
        "status": "delivered"
      },
      "store": {
        "id": 5,
        "name": "Awesome Store"
      },
      "last_message": {
        "message": "Dispute has been resolved in your favor.",
        "sender_type": "admin",
        "created_at": "2025-11-11T14:00:00.000000Z"
      }
    }
  ]
}
```

---

## 3. View Single Dispute

Retrieves detailed information about a specific dispute including all chat messages.

**Endpoint:** `GET /api/dispute/{id}`

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Dispute ID |

### Request Example

```bash
curl -X GET "https://your-api.com/api/dispute/1" \
  -H "Authorization: Bearer {token}"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "dispute": {
      "id": 1,
      "category": "Order Dispute",
      "details": "The product I received is different from what I ordered",
      "images": [
        "disputes/image1.jpg",
        "disputes/image2.jpg"
      ],
      "status": "open",
      "won_by": null,
      "resolution_notes": null,
      "created_at": "2025-11-12T15:00:00.000000Z",
      "resolved_at": null,
      "closed_at": null
    },
    "dispute_chat": {
      "id": 1,
      "buyer": {
        "id": 10,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "seller": {
        "id": 20,
        "name": "Jane Seller",
        "email": "jane@example.com"
      },
      "store": {
        "id": 5,
        "name": "Awesome Store"
      },
      "messages": [
        {
          "id": 1,
          "sender_id": 10,
          "sender_type": "buyer",
          "sender_name": "John Doe",
          "message": "📌 Dispute created: Order Dispute\n\nThe product I received is different from what I ordered",
          "image": null,
          "is_read": true,
          "created_at": "2025-11-12T15:00:00.000000Z"
        },
        {
          "id": 2,
          "sender_id": 20,
          "sender_type": "seller",
          "sender_name": "Jane Seller",
          "message": "I apologize for the inconvenience. Let me check the order details.",
          "image": null,
          "is_read": true,
          "created_at": "2025-11-12T15:30:00.000000Z"
        },
        {
          "id": 3,
          "sender_id": 1,
          "sender_type": "admin",
          "sender_name": "Admin User",
          "message": "I'm reviewing this dispute. Please provide more details if needed.",
          "image": null,
          "is_read": false,
          "created_at": "2025-11-12T16:00:00.000000Z"
        }
      ]
    },
    "store_order": {
      "id": 456,
      "order_id": 789,
      "status": "delivered",
      "items_subtotal": "100.00",
      "shipping_fee": "10.00",
      "subtotal_with_shipping": "110.00",
      "items": [
        {
          "id": 1,
          "name": "Product Name",
          "sku": "SKU123",
          "unit_price": "50.00",
          "qty": 2,
          "line_total": "100.00"
        }
      ]
    }
  }
}
```

### Error Response (404)

```json
{
  "status": "error",
  "message": "No query results for model [App\\Models\\Dispute] 1"
}
```

---

## 4. Send Message in Dispute Chat

Sends a message in the dispute chat. Only the buyer who created the dispute can send messages.

**Endpoint:** `POST /api/dispute/{id}/message`

**Content-Type:** `multipart/form-data` (if sending image)

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Dispute ID |

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | No* | Message text (required if no image) |
| `image` | file | No* | Image file (jpg, jpeg, png, webp, max 5MB) (required if no message) |

*At least one of `message` or `image` is required.

### Request Example

```bash
# Text message
curl -X POST "https://your-api.com/api/dispute/1/message" \
  -H "Authorization: Bearer {token}" \
  -F "message=Can you please check the order details?"

# Image message
curl -X POST "https://your-api.com/api/dispute/1/message" \
  -H "Authorization: Bearer {token}" \
  -F "image=@/path/to/image.jpg"

# Text + Image
curl -X POST "https://your-api.com/api/dispute/1/message" \
  -H "Authorization: Bearer {token}" \
  -F "message=Here's a photo of the damaged product" \
  -F "image=@/path/to/image.jpg"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "message": {
      "id": 4,
      "dispute_chat_id": 1,
      "sender_id": 10,
      "sender_type": "buyer",
      "message": "Can you please check the order details?",
      "image": null,
      "is_read": false,
      "created_at": "2025-11-12T17:00:00.000000Z",
      "updated_at": "2025-11-12T17:00:00.000000Z",
      "sender": {
        "id": 10,
        "full_name": "John Doe",
        "email": "john@example.com"
      }
    }
  },
  "message": "Message sent successfully."
}
```

### Error Response (422)

```json
{
  "status": "error",
  "message": "Message or image is required."
}
```

---

## 5. Mark Messages as Read

Marks all non-buyer messages (seller and admin messages) as read in the dispute chat.

**Endpoint:** `POST /api/dispute/{id}/mark-read`

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Dispute ID |

### Request Example

```bash
curl -X POST "https://your-api.com/api/dispute/1/mark-read" \
  -H "Authorization: Bearer {token}"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": [],
  "message": "Messages marked as read."
}
```

---

## Dispute Status Values

| Status | Description |
|--------|-------------|
| `open` | Dispute is newly created and open |
| `pending` | Dispute is pending admin review |
| `on_hold` | Dispute is on hold |
| `resolved` | Dispute has been resolved |
| `closed` | Dispute has been closed |

## Won By Values

| Value | Description |
|-------|-------------|
| `buyer` | Dispute resolved in favor of buyer |
| `seller` | Dispute resolved in favor of seller |
| `admin` | Admin decision (neutral/other) |

---

## Error Responses

All endpoints may return the following error responses:

### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "Dispute not found."
}
```

### 500 Internal Server Error
```json
{
  "status": "error",
  "message": "Error message here"
}
```

