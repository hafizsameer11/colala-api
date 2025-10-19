# Dispute Management API Guide

This guide covers the complete dispute management system for the Colala Mall admin panel.

## Overview

The dispute management system allows administrators to:
- View and filter all disputes
- Update dispute status
- Resolve disputes with resolution notes
- Close disputes
- Perform bulk actions
- View analytics and statistics

## API Endpoints

All endpoints are prefixed with `/api/admin/` and require authentication.

### 1. Get All Disputes

**GET** `/api/admin/disputes`

Retrieve all disputes with filtering and pagination.

#### Query Parameters:
- `status` (optional): Filter by status (`pending`, `on_hold`, `resolved`, `closed`)
- `category` (optional): Filter by dispute category
- `date_from` (optional): Start date filter (YYYY-MM-DD)
- `date_to` (optional): End date filter (YYYY-MM-DD)
- `search` (optional): Search in details, user names, or store names
- `sort_by` (optional): Sort field (default: `created_at`)
- `sort_order` (optional): Sort direction (`asc` or `desc`, default: `desc`)
- `per_page` (optional): Items per page (default: 20)

#### Response:
```json
{
  "status": "success",
  "message": "Disputes retrieved successfully.",
  "data": {
    "disputes": [
      {
        "id": 1,
        "category": "product_quality",
        "details": "Product arrived damaged",
        "images": ["image1.jpg", "image2.jpg"],
        "status": "pending",
        "won_by": null,
        "resolution_notes": null,
        "created_at": "2025-01-15T10:30:00Z",
        "updated_at": "2025-01-15T10:30:00Z",
        "resolved_at": null,
        "closed_at": null,
        "user": {
          "id": 123,
          "name": "John Doe",
          "email": "john@example.com",
          "phone": "+1234567890"
        },
        "chat": {
          "id": 456,
          "store_name": "Tech Store",
          "user_name": "John Doe",
          "last_message": "I need help with my order",
          "created_at": "2025-01-15T10:30:00Z"
        },
        "store_order": {
          "id": 789,
          "order_id": 101,
          "status": "delivered",
          "items_subtotal": 150.00,
          "shipping_fee": 10.00,
          "subtotal_with_shipping": 160.00,
          "created_at": "2025-01-15T10:30:00Z"
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
      "status": "pending",
      "category": null,
      "date_from": null,
      "date_to": null,
      "search": null
    }
  }
}
```

### 2. Get Dispute Statistics

**GET** `/api/admin/disputes/statistics`

Get comprehensive dispute statistics.

#### Query Parameters:
- `date_from` (optional): Start date for statistics (default: 30 days ago)
- `date_to` (optional): End date for statistics (default: today)

#### Response:
```json
{
  "status": "success",
  "message": "Dispute statistics retrieved successfully.",
  "data": {
    "total_disputes": 150,
    "pending_disputes": 25,
    "resolved_disputes": 100,
    "on_hold_disputes": 15,
    "recent_disputes": 30,
    "disputes_by_category": {
      "product_quality": 45,
      "delivery_issue": 30,
      "payment_problem": 25,
      "other": 20
    },
    "disputes_by_status": {
      "pending": 25,
      "on_hold": 15,
      "resolved": 100,
      "closed": 10
    }
  }
}
```

### 3. Get Dispute Details

**GET** `/api/admin/disputes/{disputeId}/details`

Get detailed information about a specific dispute.

#### Response:
```json
{
  "status": "success",
  "message": "Dispute details retrieved successfully.",
  "data": {
    "dispute": {
      "id": 1,
      "category": "product_quality",
      "details": "Product arrived damaged",
      "images": ["image1.jpg", "image2.jpg"],
      "status": "pending",
      "won_by": null,
      "resolution_notes": null,
      "created_at": "2025-01-15T10:30:00Z",
      "updated_at": "2025-01-15T10:30:00Z",
      "resolved_at": null,
      "closed_at": null,
      "user": {
        "id": 123,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+1234567890"
      },
      "chat": {
        "id": 456,
        "store_name": "Tech Store",
        "user_name": "John Doe",
        "last_message": "I need help with my order",
        "created_at": "2025-01-15T10:30:00Z",
        "recent_messages": [
          {
            "id": 1,
            "message": "I need help with my order",
            "sender_type": "user",
            "created_at": "2025-01-15T10:30:00Z"
          },
          {
            "id": 2,
            "message": "How can I help you?",
            "sender_type": "store",
            "created_at": "2025-01-15T10:35:00Z"
          }
        ]
      },
      "store_order": {
        "id": 789,
        "order_id": 101,
        "status": "delivered",
        "items_subtotal": 150.00,
        "shipping_fee": 10.00,
        "subtotal_with_shipping": 160.00,
        "created_at": "2025-01-15T10:30:00Z",
        "items": [
          {
            "id": 1,
            "name": "iPhone 15",
            "sku": "IPH15-001",
            "unit_price": 150.00,
            "qty": 1,
            "line_total": 150.00
          }
        ]
      }
    }
  }
}
```

### 4. Update Dispute Status

**PUT** `/api/admin/disputes/{disputeId}/status`

Update the status of a dispute.

#### Request Body:
```json
{
  "status": "on_hold",
  "resolution_notes": "Waiting for additional information from customer",
  "won_by": null
}
```

#### Response:
```json
{
  "status": "success",
  "message": "Dispute status updated successfully.",
  "data": {
    "dispute": {
      "id": 1,
      "status": "on_hold",
      "resolution_notes": "Waiting for additional information from customer",
      "won_by": null,
      "updated_at": "2025-01-15T11:00:00Z"
    }
  }
}
```

### 5. Resolve Dispute

**POST** `/api/admin/disputes/{disputeId}/resolve`

Resolve a dispute with resolution notes and winner.

#### Request Body:
```json
{
  "resolution_notes": "Issue resolved in favor of customer. Full refund processed.",
  "won_by": "buyer"
}
```

#### Response:
```json
{
  "status": "success",
  "message": "Dispute resolved successfully.",
  "data": {
    "dispute": {
      "id": 1,
      "status": "resolved",
      "won_by": "buyer",
      "resolution_notes": "Issue resolved in favor of customer. Full refund processed.",
      "resolved_at": "2025-01-15T12:00:00Z"
    }
  }
}
```

### 6. Close Dispute

**POST** `/api/admin/disputes/{disputeId}/close`

Close a dispute.

#### Request Body:
```json
{
  "resolution_notes": "Dispute closed after resolution"
}
```

#### Response:
```json
{
  "status": "success",
  "message": "Dispute closed successfully.",
  "data": {
    "dispute": {
      "id": 1,
      "status": "closed",
      "resolution_notes": "Dispute closed after resolution",
      "closed_at": "2025-01-15T13:00:00Z"
    }
  }
}
```

### 7. Bulk Actions

**POST** `/api/admin/disputes/bulk-action`

Perform bulk actions on multiple disputes.

#### Request Body:
```json
{
  "action": "update_status",
  "dispute_ids": [1, 2, 3, 4, 5],
  "status": "on_hold",
  "resolution_notes": "Bulk status update"
}
```

#### Available Actions:
- `update_status`: Update status of selected disputes
- `resolve`: Resolve disputes (requires `won_by`)
- `close`: Close disputes

#### Response:
```json
{
  "status": "success",
  "message": "Bulk action completed successfully. 5 disputes updated.",
  "data": {
    "updated_count": 5,
    "action": "update_status"
  }
}
```

### 8. Get Dispute Analytics

**GET** `/api/admin/disputes/analytics`

Get detailed analytics and trends for disputes.

#### Query Parameters:
- `date_from` (optional): Start date for analytics (default: 30 days ago)
- `date_to` (optional): End date for analytics (default: today)

#### Response:
```json
{
  "status": "success",
  "message": "Dispute analytics retrieved successfully.",
  "data": {
    "trends": [
      {
        "date": "2025-01-15",
        "count": 5
      },
      {
        "date": "2025-01-16",
        "count": 8
      }
    ],
    "resolution_times": {
      "avg_resolution_hours": 24.5,
      "min_resolution_hours": 2,
      "max_resolution_hours": 72
    },
    "top_categories": [
      {
        "category": "product_quality",
        "count": 25
      },
      {
        "category": "delivery_issue",
        "count": 15
      }
    ],
    "outcomes": [
      {
        "won_by": "buyer",
        "count": 30
      },
      {
        "won_by": "seller",
        "count": 20
      },
      {
        "won_by": "admin",
        "count": 10
      }
    ],
    "date_range": {
      "from": "2025-01-01",
      "to": "2025-01-15"
    }
  }
}
```

## Status Values

- `pending`: Dispute is awaiting review
- `on_hold`: Dispute is on hold (waiting for information)
- `resolved`: Dispute has been resolved
- `closed`: Dispute has been closed

## Won By Values

- `buyer`: Dispute resolved in favor of the buyer
- `seller`: Dispute resolved in favor of the seller
- `admin`: Admin decision (neutral or special case)

## Error Responses

All endpoints return appropriate HTTP status codes and error messages:

```json
{
  "status": "error",
  "message": "Dispute not found.",
  "data": null
}
```

## Authentication

All endpoints require authentication via Sanctum token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Rate Limiting

Standard API rate limiting applies to all endpoints.

## Usage Examples

### Get Pending Disputes
```bash
curl -X GET "https://api.colala.com/admin/disputes?status=pending" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Resolve Dispute
```bash
curl -X POST "https://api.colala.com/admin/disputes/1/resolve" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "resolution_notes": "Customer refunded due to product defect",
    "won_by": "buyer"
  }'
```

### Bulk Update Status
```bash
curl -X POST "https://api.colala.com/admin/disputes/bulk-action" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_status",
    "dispute_ids": [1, 2, 3],
    "status": "on_hold",
    "resolution_notes": "Waiting for customer response"
  }'
```
