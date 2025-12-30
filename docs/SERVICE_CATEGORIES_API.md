# Service Categories API Documentation

Complete API documentation for Service Categories management endpoints.

## Base URL
- **Production:** `https://colala.hmstech.xyz/api`
- **Admin Base:** `https://colala.hmstech.xyz/api/admin`

## Authentication
All admin endpoints require authentication via Bearer token:
```
Authorization: Bearer {your_token}
```

---

## Endpoints Overview

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|--------------|
| GET | `/api/service-categories` | Get all service categories (public) | No |
| GET | `/api/service-categories/{id}` | Get single category (public) | No |
| GET | `/api/admin/service-categories` | Get all categories (admin, paginated) | Yes |
| GET | `/api/admin/service-categories/{id}` | Get single category (admin) | Yes |
| POST | `/api/admin/service-categories` | Create new category | Yes |
| PUT | `/api/admin/service-categories/{id}` | Update category | Yes |
| DELETE | `/api/admin/service-categories/{id}` | Delete category | Yes |

---

## 1. Get All Service Categories (Public)

### Endpoint
```
GET /api/service-categories
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number |
| `per_page` | integer | No | 15 | Items per page (max 100) |
| `is_active` | boolean | No | - | Filter by active status |

### Example Requests

**Get all categories (no pagination):**
```bash
GET /api/service-categories
```

**Get paginated categories:**
```bash
GET /api/service-categories?page=1&per_page=15
```

**Get only active categories:**
```bash
GET /api/service-categories?is_active=true&page=1&per_page=15
```

### Response (Paginated)

**Status Code:** `200 OK`

```json
{
  "status": true,
  "data": {
    "data": [
      {
        "id": 1,
        "title": "Building & Trades Services",
        "image": "service_categories/abc123.jpg",
        "image_url": "https://colala.hmstech.xyz/storage/service_categories/abc123.jpg",
        "is_active": true,
        "services_count": 45,
        "created_at": "2025-01-15T10:30:00.000000Z",
        "updated_at": "2025-01-15T10:30:00.000000Z"
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 25,
    "last_page": 2,
    "from": 1,
    "to": 15
  }
}
```

### Response (No Pagination)

**Status Code:** `200 OK`

```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "title": "Building & Trades Services",
      "image": "service_categories/abc123.jpg",
      "image_url": "https://colala.hmstech.xyz/storage/service_categories/abc123.jpg",
      "is_active": true,
      "services_count": 45,
      "created_at": "2025-01-15T10:30:00.000000Z",
      "updated_at": "2025-01-15T10:30:00.000000Z"
    }
  ]
}
```

### Error Response

**Status Code:** `500 Internal Server Error`

```json
{
  "status": false,
  "message": "Error message here"
}
```

---

## 2. Get Single Service Category (Public)

### Endpoint
```
GET /api/service-categories/{id}
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Category ID |

### Example Request

```bash
GET /api/service-categories/1
```

### Response

**Status Code:** `200 OK`

```json
{
  "status": true,
  "data": {
    "id": 1,
    "title": "Building & Trades Services",
    "image": "service_categories/abc123.jpg",
    "image_url": "https://colala.hmstech.xyz/storage/service_categories/abc123.jpg",
    "is_active": true,
    "services_count": 45,
    "services": [
      {
        "id": 1,
        "name": "Plumbing Services",
        "store": {
          "id": 5,
          "store_name": "ABC Plumbing"
        },
        "media": [],
        "sub_services": []
      }
    ],
    "created_at": "2025-01-15T10:30:00.000000Z",
    "updated_at": "2025-01-15T10:30:00.000000Z"
  }
}
```

### Error Response

**Status Code:** `404 Not Found`

```json
{
  "status": false,
  "message": "No query results for model [App\\Models\\ServiceCategory] 999"
}
```

---

## 3. Get All Service Categories (Admin - Paginated)

### Endpoint
```
GET /api/admin/service-categories
```

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number |
| `per_page` | integer | No | 15 | Items per page (max 100) |
| `is_active` | boolean | No | - | Filter by active status |

### Example Request

```bash
GET /api/admin/service-categories?page=1&per_page=15
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Response

Same format as public endpoint (see section 1).

---

## 4. Get Single Service Category (Admin)

### Endpoint
```
GET /api/admin/service-categories/{id}
```

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Category ID |

### Example Request

```bash
GET /api/admin/service-categories/1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Response

Same format as public endpoint (see section 2).

---

## 5. Create Service Category

### Endpoint
```
POST /api/admin/service-categories
```

### Headers

```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Category title (max 255 chars) |
| `image` | file | No | Category image (jpg, jpeg, png, max 5MB) |
| `is_active` | boolean | No | Active status (default: true) |

### Example Request

**Using cURL:**
```bash
curl -X POST https://colala.hmstech.xyz/api/admin/service-categories \
  -H "Authorization: Bearer {token}" \
  -F "title=New Service Category" \
  -F "is_active=true" \
  -F "image=@/path/to/image.jpg"
```

**Using JavaScript (FormData):**
```javascript
const formData = new FormData();
formData.append('title', 'New Service Category');
formData.append('is_active', true);
formData.append('image', fileInput.files[0]);

fetch('https://colala.hmstech.xyz/api/admin/service-categories', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});
```

### Response

**Status Code:** `200 OK`

```json
{
  "status": true,
  "message": "Category created successfully.",
  "data": {
    "id": 26,
    "title": "New Service Category",
    "image": "service_categories/xyz789.jpg",
    "image_url": "https://colala.hmstech.xyz/storage/service_categories/xyz789.jpg",
    "is_active": true,
    "created_at": "2025-01-20T14:30:00.000000Z",
    "updated_at": "2025-01-20T14:30:00.000000Z"
  }
}
```

### Error Response

**Status Code:** `422 Unprocessable Entity`

```json
{
  "status": false,
  "message": "The title field is required."
}
```

**Status Code:** `401 Unauthorized`

```json
{
  "message": "Unauthenticated."
}
```

---

## 6. Update Service Category

### Endpoint
```
PUT /api/admin/service-categories/{id}
```

### Headers

```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Category ID |

### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Category title (max 255 chars) |
| `image` | file | No | Category image (jpg, jpeg, png, max 5MB) |
| `is_active` | boolean | No | Active status |

**Note:** If `image` is provided, the old image will be automatically deleted.

### Example Request

**Using cURL:**
```bash
curl -X PUT https://colala.hmstech.xyz/api/admin/service-categories/1 \
  -H "Authorization: Bearer {token}" \
  -F "title=Updated Category Name" \
  -F "is_active=true" \
  -F "image=@/path/to/new-image.jpg"
```

**Using JavaScript (FormData):**
```javascript
const formData = new FormData();
formData.append('title', 'Updated Category Name');
formData.append('is_active', true);
formData.append('image', fileInput.files[0]);

fetch('https://colala.hmstech.xyz/api/admin/service-categories/1', {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});
```

### Response

**Status Code:** `200 OK`

```json
{
  "status": true,
  "message": "Category updated successfully.",
  "data": {
    "id": 1,
    "title": "Updated Category Name",
    "image": "service_categories/new-abc123.jpg",
    "image_url": "https://colala.hmstech.xyz/storage/service_categories/new-abc123.jpg",
    "is_active": true,
    "created_at": "2025-01-15T10:30:00.000000Z",
    "updated_at": "2025-01-20T15:45:00.000000Z"
  }
}
```

### Error Response

**Status Code:** `404 Not Found`

```json
{
  "status": false,
  "message": "No query results for model [App\\Models\\ServiceCategory] 999"
}
```

**Status Code:** `422 Unprocessable Entity`

```json
{
  "status": false,
  "message": "The title field is required."
}
```

---

## 7. Delete Service Category

### Endpoint
```
DELETE /api/admin/service-categories/{id}
```

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Category ID |

### Example Request

**Using cURL:**
```bash
curl -X DELETE https://colala.hmstech.xyz/api/admin/service-categories/1 \
  -H "Authorization: Bearer {token}"
```

**Using JavaScript:**
```javascript
fetch('https://colala.hmstech.xyz/api/admin/service-categories/1', {
  method: 'DELETE',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
```

### Response

**Status Code:** `200 OK`

```json
{
  "status": true,
  "message": "Category deleted successfully.",
  "data": null
}
```

### Error Response

**Status Code:** `404 Not Found`

```json
{
  "status": false,
  "message": "No query results for model [App\\Models\\ServiceCategory] 999"
}
```

---

## Data Models

### ServiceCategory Object

```typescript
interface ServiceCategory {
  id: number;
  title: string;
  image: string | null;
  image_url: string | null;
  is_active: boolean;
  services_count?: number;
  services?: Service[];
  created_at: string; // ISO 8601 datetime
  updated_at: string; // ISO 8601 datetime
}
```

### Pagination Object

```typescript
interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

interface PaginatedResponse<T> {
  status: boolean;
  data: {
    data: T[];
  } & PaginationMeta;
}
```

---

## Error Codes

| Status Code | Description |
|-------------|-------------|
| `200` | Success |
| `401` | Unauthorized - Missing or invalid token |
| `404` | Not Found - Resource doesn't exist |
| `422` | Validation Error - Invalid request data |
| `500` | Internal Server Error |

---

## Validation Rules

### Create/Update Request

- **title**: Required, string, max 255 characters
- **image**: Optional, must be image file (jpg, jpeg, png), max 5MB
- **is_active**: Optional, boolean

---

## Example Integration (React/TypeScript)

```typescript
// types.ts
interface ServiceCategory {
  id: number;
  title: string;
  image: string | null;
  image_url: string | null;
  is_active: boolean;
  services_count?: number;
  created_at: string;
  updated_at: string;
}

interface PaginatedResponse<T> {
  status: boolean;
  data: {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
  };
}

// api.ts
const API_BASE = 'https://colala.hmstech.xyz/api';
const ADMIN_BASE = `${API_BASE}/admin`;

class ServiceCategoryAPI {
  private token: string;

  constructor(token: string) {
    this.token = token;
  }

  // Get all categories (public)
  async getAll(params?: { page?: number; per_page?: number; is_active?: boolean }) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', params.page.toString());
    if (params?.per_page) queryParams.append('per_page', params.per_page.toString());
    if (params?.is_active !== undefined) queryParams.append('is_active', params.is_active.toString());

    const response = await fetch(`${API_BASE}/service-categories?${queryParams}`);
    return response.json();
  }

  // Get single category (public)
  async getById(id: number) {
    const response = await fetch(`${API_BASE}/service-categories/${id}`);
    return response.json();
  }

  // Create category (admin)
  async create(data: { title: string; image?: File; is_active?: boolean }) {
    const formData = new FormData();
    formData.append('title', data.title);
    if (data.image) formData.append('image', data.image);
    if (data.is_active !== undefined) formData.append('is_active', data.is_active.toString());

    const response = await fetch(`${ADMIN_BASE}/service-categories`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`
      },
      body: formData
    });
    return response.json();
  }

  // Update category (admin)
  async update(id: number, data: { title: string; image?: File; is_active?: boolean }) {
    const formData = new FormData();
    formData.append('title', data.title);
    if (data.image) formData.append('image', data.image);
    if (data.is_active !== undefined) formData.append('is_active', data.is_active.toString());

    const response = await fetch(`${ADMIN_BASE}/service-categories/${id}`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${this.token}`
      },
      body: formData
    });
    return response.json();
  }

  // Delete category (admin)
  async delete(id: number) {
    const response = await fetch(`${ADMIN_BASE}/service-categories/${id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json'
      }
    });
    return response.json();
  }
}

// Usage
const api = new ServiceCategoryAPI('your_token_here');

// Get paginated categories
const categories = await api.getAll({ page: 1, per_page: 15 });

// Create new category
const newCategory = await api.create({
  title: 'New Category',
  is_active: true,
  image: fileInput.files[0]
});

// Update category
const updated = await api.update(1, {
  title: 'Updated Title',
  is_active: false
});

// Delete category
await api.delete(1);
```

---

## Notes

1. **Image Upload**: When uploading images, use `multipart/form-data` content type
2. **Image Deletion**: Old images are automatically deleted when updating with a new image
3. **Pagination**: Maximum `per_page` value is 100
4. **Authentication**: All admin endpoints require valid Bearer token
5. **Image URLs**: Always use `image_url` field for displaying images in frontend
6. **Services Count**: Included in list responses for performance

---

## Support

For issues or questions, contact the backend team.

