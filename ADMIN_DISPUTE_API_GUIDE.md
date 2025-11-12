# Admin Dispute API Integration Guide

## Base URL
```
/admin
```

## Authentication
All endpoints require authentication. Include the bearer token in the Authorization header:
```
Authorization: Bearer {your_token}
```

**Note:** Only users with admin role can access these endpoints.

---

## 1. Get All Disputes

Retrieves all disputes with filtering, pagination, and search capabilities.

**Endpoint:** `GET /admin/disputes`

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by status: `open`, `pending`, `on_hold`, `resolved`, `closed` |
| `category` | string | No | Filter by dispute category |
| `date_from` | date | No | Filter disputes from this date (format: YYYY-MM-DD) |
| `date_to` | date | No | Filter disputes to this date (format: YYYY-MM-DD) |
| `search` | string | No | Search in dispute details, user name/email, or store name |
| `sort_by` | string | No | Sort field (default: `created_at`) |
| `sort_order` | string | No | Sort order: `asc` or `desc` (default: `desc`) |
| `per_page` | integer | No | Items per page (default: 20) |
| `page` | integer | No | Page number (default: 1) |

### Request Example

```bash
# Get all disputes
curl -X GET "https://your-api.com/admin/disputes" \
  -H "Authorization: Bearer {token}"

# Filter by status
curl -X GET "https://your-api.com/admin/disputes?status=open" \
  -H "Authorization: Bearer {token}"

# Search disputes
curl -X GET "https://your-api.com/admin/disputes?search=order" \
  -H "Authorization: Bearer {token}"

# Filter by date range
curl -X GET "https://your-api.com/admin/disputes?date_from=2025-11-01&date_to=2025-11-30" \
  -H "Authorization: Bearer {token}"

# Combined filters with pagination
curl -X GET "https://your-api.com/admin/disputes?status=open&per_page=10&page=1&sort_by=created_at&sort_order=desc" \
  -H "Authorization: Bearer {token}"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "disputes": [
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
        "updated_at": "2025-11-12T15:00:00.000000Z",
        "resolved_at": null,
        "closed_at": null,
        "user": {
          "id": 10,
          "name": "John Doe",
          "email": "john@example.com",
          "phone": "+1234567890",
          "profile_picture": "https://your-api.com/storage/profile.jpg"
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
          }
        },
        "store_order": {
          "id": 456,
          "order_id": 789,
          "status": "delivered",
          "items_subtotal": "100.00",
          "shipping_fee": "10.00",
          "subtotal_with_shipping": "110.00",
          "created_at": "2025-11-10T10:00:00.000000Z"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100
    },
    "filters": {
      "status": "open",
      "category": null,
      "date_from": null,
      "date_to": null,
      "search": null
    }
  },
  "message": "Disputes retrieved successfully."
}
```

---

## 2. Get Dispute Statistics

Retrieves statistics about disputes.

**Endpoint:** `GET /admin/disputes/statistics`

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date_from` | date | No | Start date for recent disputes (default: 30 days ago) |
| `date_to` | date | No | End date for recent disputes (default: today) |

### Request Example

```bash
curl -X GET "https://your-api.com/admin/disputes/statistics" \
  -H "Authorization: Bearer {token}"

# With date range
curl -X GET "https://your-api.com/admin/disputes/statistics?date_from=2025-11-01&date_to=2025-11-30" \
  -H "Authorization: Bearer {token}"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "total_disputes": 150,
    "pending_disputes": 25,
    "resolved_disputes": 100,
    "on_hold_disputes": 10,
    "recent_disputes": 45,
    "disputes_by_category": {
      "Order Dispute": 50,
      "Late Delivery": 30,
      "Wrong Item": 25,
      "Damaged Product": 20,
      "Other": 25
    },
    "disputes_by_status": {
      "open": 15,
      "pending": 25,
      "on_hold": 10,
      "resolved": 100,
      "closed": 0
    }
  },
  "message": "Dispute statistics retrieved successfully."
}
```

---

## 3. Get Dispute Details

Retrieves detailed information about a specific dispute including all chat messages and order details.

**Endpoint:** `GET /admin/disputes/{disputeId}/details`

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `disputeId` | integer | Dispute ID |

### Request Example

```bash
curl -X GET "https://your-api.com/admin/disputes/1/details" \
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
      "updated_at": "2025-11-12T15:00:00.000000Z",
      "resolved_at": null,
      "closed_at": null,
      "user": {
        "id": 10,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+1234567890",
        "profile_picture": "https://your-api.com/storage/profile.jpg"
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
            "is_read": true,
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
        "created_at": "2025-11-10T10:00:00.000000Z",
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
  },
  "message": "Dispute details retrieved successfully."
}
```

---

## 4. Update Dispute Status

Updates the status of a dispute.

**Endpoint:** `PUT /admin/disputes/{disputeId}/status`

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `disputeId` | integer | Dispute ID |

### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | Yes | New status: `pending`, `on_hold`, `resolved`, `closed` |
| `resolution_notes` | string | No | Notes about the status change (max 1000 chars) |
| `won_by` | string | No | Who won: `buyer`, `seller`, or `admin` |

### Request Example

```bash
curl -X PUT "https://your-api.com/admin/disputes/1/status" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "on_hold",
    "resolution_notes": "Waiting for additional information from buyer"
  }'
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
      "images": ["disputes/image1.jpg"],
      "status": "on_hold",
      "won_by": null,
      "resolution_notes": "Waiting for additional information from buyer",
      "created_at": "2025-11-12T15:00:00.000000Z",
      "updated_at": "2025-11-12T18:00:00.000000Z"
    }
  },
  "message": "Dispute status updated successfully."
}
```

---

## 5. Resolve Dispute

Resolves a dispute with a winner and resolution notes.

**Endpoint:** `POST /admin/disputes/{disputeId}/resolve`

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `disputeId` | integer | Dispute ID |

### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `resolution_notes` | string | Yes | Detailed resolution notes (max 1000 chars) |
| `won_by` | string | Yes | Who won: `buyer`, `seller`, or `admin` |

### Request Example

```bash
curl -X POST "https://your-api.com/admin/disputes/1/resolve" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "resolution_notes": "After reviewing the evidence, the buyer is correct. The wrong item was shipped. Full refund issued.",
    "won_by": "buyer"
  }'
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
      "images": ["disputes/image1.jpg"],
      "status": "resolved",
      "won_by": "buyer",
      "resolution_notes": "After reviewing the evidence, the buyer is correct. The wrong item was shipped. Full refund issued.",
      "created_at": "2025-11-12T15:00:00.000000Z",
      "updated_at": "2025-11-12T19:00:00.000000Z",
      "resolved_at": "2025-11-12T19:00:00.000000Z"
    }
  },
  "message": "Dispute resolved successfully."
}
```

---

## 6. Close Dispute

Closes a dispute without resolving it.

**Endpoint:** `POST /admin/disputes/{disputeId}/close`

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `disputeId` | integer | Dispute ID |

### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `resolution_notes` | string | No | Notes about closing the dispute (max 1000 chars) |

### Request Example

```bash
curl -X POST "https://your-api.com/admin/disputes/1/close" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "resolution_notes": "Dispute closed - parties reached agreement outside platform"
  }'
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "dispute": {
      "id": 1,
      "status": "closed",
      "resolution_notes": "Dispute closed - parties reached agreement outside platform",
      "closed_at": "2025-11-12T20:00:00.000000Z"
    }
  },
  "message": "Dispute closed successfully."
}
```

---

## 7. Bulk Actions on Disputes

Performs bulk actions on multiple disputes.

**Endpoint:** `POST /admin/disputes/bulk-action`

### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Action to perform: `update_status`, `resolve`, `close` |
| `dispute_ids` | array | Yes | Array of dispute IDs |
| `status` | string | Conditional | Required if `action` is `update_status`. Values: `pending`, `on_hold`, `resolved`, `closed` |
| `won_by` | string | Conditional | Required if `action` is `resolve`. Values: `buyer`, `seller`, `admin` |
| `resolution_notes` | string | No | Notes for the action (max 1000 chars) |

### Request Example

```bash
# Bulk update status
curl -X POST "https://your-api.com/admin/disputes/bulk-action" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_status",
    "dispute_ids": [1, 2, 3],
    "status": "on_hold",
    "resolution_notes": "Bulk update - reviewing all disputes"
  }'

# Bulk resolve
curl -X POST "https://your-api.com/admin/disputes/bulk-action" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "resolve",
    "dispute_ids": [1, 2, 3],
    "won_by": "buyer",
    "resolution_notes": "Bulk resolution - all resolved in favor of buyer"
  }'
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "updated_count": 3,
    "action": "update_status"
  },
  "message": "Bulk action completed successfully. 3 disputes updated."
}
```

---

## 8. Get Dispute Analytics

Retrieves analytics and trends for disputes.

**Endpoint:** `GET /admin/disputes/analytics`

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date_from` | date | No | Start date (default: 30 days ago) |
| `date_to` | date | No | End date (default: today) |

### Request Example

```bash
curl -X GET "https://your-api.com/admin/disputes/analytics" \
  -H "Authorization: Bearer {token}"

# With date range
curl -X GET "https://your-api.com/admin/disputes/analytics?date_from=2025-11-01&date_to=2025-11-30" \
  -H "Authorization: Bearer {token}"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "trends": [
      {
        "date": "2025-11-01",
        "count": 5
      },
      {
        "date": "2025-11-02",
        "count": 8
      }
    ],
    "resolution_times": {
      "avg_resolution_hours": 48.5,
      "min_resolution_hours": 2,
      "max_resolution_hours": 168
    },
    "top_categories": [
      {
        "category": "Order Dispute",
        "count": 50
      },
      {
        "category": "Late Delivery",
        "count": 30
      }
    ],
    "outcomes": [
      {
        "won_by": "buyer",
        "count": 60
      },
      {
        "won_by": "seller",
        "count": 30
      },
      {
        "won_by": "admin",
        "count": 10
      }
    ],
    "date_range": {
      "from": "2025-11-01",
      "to": "2025-11-30"
    }
  },
  "message": "Dispute analytics retrieved successfully."
}
```

---

## 9. Get Dispute Chat Messages

Retrieves all messages in a dispute chat.

**Endpoint:** `GET /admin/disputes/{disputeId}/chat`

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `disputeId` | integer | Dispute ID |

### Request Example

```bash
curl -X GET "https://your-api.com/admin/disputes/1/chat" \
  -H "Authorization: Bearer {token}"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
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
          "message": "📌 Dispute created: Order Dispute",
          "image": null,
          "is_read": true,
          "created_at": "2025-11-12T15:00:00.000000Z"
        },
        {
          "id": 2,
          "sender_id": 20,
          "sender_type": "seller",
          "sender_name": "Jane Seller",
          "message": "I apologize for the inconvenience.",
          "image": null,
          "is_read": true,
          "created_at": "2025-11-12T15:30:00.000000Z"
        }
      ]
    }
  },
  "message": "Dispute chat messages retrieved successfully."
}
```

---

## 10. Send Message in Dispute Chat (Admin)

Sends a message in the dispute chat as an admin. When admin sends a message, all messages are automatically marked as read.

**Endpoint:** `POST /admin/disputes/{disputeId}/message`

**Content-Type:** `multipart/form-data` (if sending image)

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `disputeId` | integer | Dispute ID |

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | No* | Message text (required if no image) |
| `image` | file | No* | Image file (jpg, jpeg, png, webp, max 5MB) (required if no message) |

*At least one of `message` or `image` is required.

### Request Example

```bash
# Text message
curl -X POST "https://your-api.com/admin/disputes/1/message" \
  -H "Authorization: Bearer {token}" \
  -F "message=I'm reviewing this dispute. Please provide any additional information."

# Image message
curl -X POST "https://your-api.com/admin/disputes/1/message" \
  -H "Authorization: Bearer {token}" \
  -F "image=@/path/to/document.jpg"
```

### Success Response (200)

```json
{
  "status": "success",
  "data": {
    "message": {
      "id": 4,
      "dispute_chat_id": 1,
      "sender_id": 1,
      "sender_type": "admin",
      "message": "I'm reviewing this dispute. Please provide any additional information.",
      "image": null,
      "is_read": true,
      "created_at": "2025-11-12T20:00:00.000000Z",
      "updated_at": "2025-11-12T20:00:00.000000Z",
      "sender": {
        "id": 1,
        "full_name": "Admin User",
        "email": "admin@example.com"
      }
    }
  },
  "message": "Message sent successfully."
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

## Sender Types in Messages

| Type | Description |
|------|-------------|
| `buyer` | Message sent by the buyer |
| `seller` | Message sent by the seller |
| `admin` | Message sent by an admin |

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

### 422 Validation Error
```json
{
  "status": "error",
  "message": "The status field is required."
}
```

### 500 Internal Server Error
```json
{
  "status": "error",
  "message": "Failed to update dispute status."
}
```

