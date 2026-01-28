## Admin Seller Onboarding – Edit Routes

This document describes the **admin-only edit/update APIs** for seller onboarding, separate from the initial create (`/complete`) routes.

- **Base URL**: `https://colala.hmstech.xyz/api/admin`
- **Auth**: `Authorization: Bearer <token>` (Sanctum)
- **Content types**:
  - `application/json` for JSON-only requests
  - `multipart/form-data` when uploading files (images, documents, videos)

---

## 1. Level 1 – Edit Basic Store Information

**Purpose**: Update an existing seller’s basic info, store profile/media, categories, and social links.

- **Route**: `POST /create-seller/level1/update`
- **Controller**: `AdminSellerCreationController@level1Update`
- **Expected content type**: `multipart/form-data` (to allow file uploads)

### 1.1 Request Body

All fields are **optional** except `store_id`. Only provided fields will be updated.

```jsonc
{
  "store_id": 12,                          // required, existing store ID

  // Basic store + user info (optional)
  "store_name": "Updated Store Name",      // optional
  "store_email": "updated@example.com",    // optional, must be unique for users.email + stores.store_email
  "store_phone": "+234...",                // optional
  "store_location": "Lagos, Nigeria",      // optional
  "referral_code": "REF123",               // optional
  "show_phone_on_profile": true,           // optional, boolean

  // Files (optional, send as multipart/form-data)
  "profile_image": (file),                 // optional image (jpeg/png/jpg/gif, max 2MB)
  "banner_image": (file),                  // optional image (jpeg/png/jpg/gif, max 2MB)

  // Categories (optional)
  "categories": [1, 2, 3],                 // optional, array of category IDs; if sent, replaces existing categories

  // Social links (optional)
  "social_links": [                        // optional; if sent, replaces existing links
    {
      "type": "instagram",                 // required when social_links is present
      "url": "https://instagram.com/..."   // required, valid URL
    }
  ]
}
```

### 1.2 Behavior

- Looks up `Store` by `store_id` and loads its `User`.
- If `store_name`, `store_email`, `store_phone` are provided:
  - Updates both `User` and `Store` records accordingly.
  - `store_email` is validated to be unique per user.
- If `profile_image` / `banner_image` are provided:
  - Stored under `storage/app/public/stores/{store_id}`.
  - `profile_image` / `banner_image` columns on `stores` are updated.
- If `categories` is provided:
  - `store_categories` pivot is **synced** to the provided IDs (existing links are replaced).
- If `social_links` is provided:
  - Existing `StoreSocialLink` records for this store are **deleted**, then re-created from the array.
- On success:
  - Level 1 onboarding steps are (re)marked as **done**:
    - `level1.basic`
    - `level1.profile_media`
    - `level1.categories_social`

### 1.3 Response (success)

```json
{
  "success": true,
  "message": "Level 1 updated successfully",
  "data": {
    "store_id": 12,
    "store_name": "Updated Store Name",
    "store_email": "updated@example.com",
    "store_phone": "+234...",
    "store_location": "Lagos, Nigeria",
    "referral_code": "REF123",
    "profile_image": "https://colala.hmstech.xyz/storage/stores/12/profile.jpg",
    "banner_image": "https://colala.hmstech.xyz/storage/stores/12/banner.jpg",
    "categories": [
      { "id": 1, "title": "Category 1", "image_url": "..." }
    ],
    "social_links": [
      { "id": 10, "type": "instagram", "url": "https://instagram.com/..." }
    ],
    "progress": {
      "level": 1,
      "percent": 30,
      "status": "pending_review"
    }
  }
}
```

On validation error (e.g. duplicate email), the API returns:

```json
{
  "success": false,
  "message": "Validation error message here"
}
```

---

## 2. Level 2 – Edit Business Details & Documents

**Purpose**: Update an existing seller’s business registration details and uploaded compliance documents.

- **Route**: `POST /create-seller/level2/update`
- **Controller**: `AdminSellerCreationController@level2Update`
- **Expected content type**: `multipart/form-data` (documents are files)

### 2.1 Request Body

All fields other than `store_id` are optional; only sent fields are updated.

```jsonc
{
  "store_id": 12,                           // required

  // Business details (optional)
  "business_name": "Registered Business",   // optional
  "business_type": "Limited Liability Company", // optional; mapped internally to enum values
  "nin_number": "1234567890",               // optional
  "cac_number": "RC123456",                 // optional

  // Documents (optional, as files via multipart/form-data)
  "nin_document": (file),                   // optional, pdf/jpeg/png/jpg, max 2MB
  "cac_document": (file),                   // optional, pdf/jpeg/png/jpg, max 2MB
  "utility_bill": (file),                   // optional, pdf/jpeg/png/jpg, max 2MB
  "store_video": (file)                     // optional, mp4/avi/mov, max 5MB
}
```

### 2.2 Behavior

- Looks up `Store` by `store_id`.
- Builds a payload for `StoreBusinessDetail`:
  - `registered_name` (from `business_name` if provided).
  - `business_type` (mapped via `mapBusinessType()`).
  - `nin_number`, `cac_number`.
  - File fields stored under `storage/app/public/stores/{store_id}`.
- Uses `StoreBusinessDetail::updateOrCreate(['store_id' => store_id], $payload)`.
- Marks Level 2 onboarding steps as **done**:
  - `level2.business_details`
  - `level2.documents`

### 2.3 Response (success)

```json
{
  "success": true,
  "message": "Level 2 updated successfully",
  "data": {
    "store_id": 12,
    "business_details": {
      "store_id": 12,
      "registered_name": "Registered Business",
      "business_type": "LTD",      // mapped internal enum
      "nin_number": "1234567890",
      "cac_number": "RC123456",
      "nin_document": "stores/12/nin.pdf",
      "cac_document": "stores/12/cac.pdf",
      "utility_bill": "stores/12/utility.jpg",
      "store_video": "stores/12/video.mp4",
      "...": "..."
    },
    "progress": {
      "level": 2,
      "percent": 60,
      "status": "pending_review"
    }
  }
}
```

---

## 3. Level 3 – Edit Physical Store, Addresses, Delivery, Theme

**Purpose**: Update an existing seller’s physical store config, addresses, delivery pricing, and theme color.

- **Route**: `POST /create-seller/level3/update`
- **Controller**: `AdminSellerCreationController@level3Update`
- **Expected content type**: `multipart/form-data` if sending files; `application/json` otherwise.

### 3.1 Request Body

All fields other than `store_id` are optional.

```jsonc
{
  "store_id": 12,                    // required

  // Physical store & documents (optional)
  "has_physical_store": true,        // optional, boolean
  "store_video": (file),             // optional, mp4/avi/mov, max 10MB
  "utility_bill": (file),            // optional, pdf/jpeg/png/jpg, max 5MB

  // Theme (optional)
  "theme_color": "#00AAFF",          // optional, hex color (#RRGGBB)

  // Addresses (optional)
  "addresses": [                     // optional; if present, replaces ALL existing addresses
    {
      "state": "Lagos",
      "local_government": "Ikeja",
      "full_address": "Full address text",
      "is_main": true,               // optional, default false
      "opening_hours": {             // optional, free-form object/array
        "mon": "9-5",
        "tue": "9-5"
      }
    }
  ],

  // Delivery pricing (optional)
  "delivery_pricing": [              // optional; if present, replaces ALL existing delivery pricing
    {
      "state": "Lagos",
      "local_government": "Ikeja",
      "variant": "light|medium|heavy|small|large|standard|express|regular|bulk", // mapped internally
      "price": 1500,
      "is_free": false
    }
  ]
}
```

### 3.2 Behavior

- Looks up `Store` by `store_id`.
- Updates `StoreBusinessDetail` with:
  - `has_physical_store` (if provided).
  - New `store_video` / `utility_bill` file paths if files are uploaded.
- Updates `Store.theme_color` if `theme_color` is provided.
- **Addresses**:
  - If `addresses` is present:
    - Deletes existing `StoreAddress` records for this store.
    - Re-creates addresses from the array.
- **Delivery pricing**:
  - If `delivery_pricing` is present:
    - Deletes existing `StoreDeliveryPricing` records for this store.
    - Re-creates pricing rows from the array (variant mapped via `mapDeliveryVariant()`).
- Marks Level 3 steps as **done**:
  - `level3.physical_store`
  - `level3.utility_bill`
  - `level3.addresses`
  - `level3.delivery_pricing`
  - `level3.theme`

### 3.3 Response (success)

```json
{
  "success": true,
  "message": "Level 3 updated successfully",
  "data": {
    "store_id": 12,
    "business_details": {
      "has_physical_store": true,
      "store_video": "stores/12/video.mp4",
      "utility_bill": "stores/12/utility.jpg",
      "...": "..."
    },
    "theme_color": "#00AAFF",
    "addresses": [
      {
        "id": 1,
        "state": "Lagos",
        "local_government": "Ikeja",
        "full_address": "Full address text",
        "is_main": true,
        "opening_hours": {
          "mon": "9-5"
        },
        "created_at": "01-01-2025 10:20:30"
      }
    ],
    "delivery_pricing": [
      {
        "id": 1,
        "state": "Lagos",
        "local_government": "Ikeja",
        "variant": "light",                // normalized
        "price": 1500,
        "formatted_price": "N1,500",
        "is_free": false,
        "created_at": "01-01-2025 10:20:30"
      }
    ],
    "progress": {
      "level": 3,
      "percent": 100,
      "status": "pending_review"
    }
  }
}
```

---

## 4. Recommended Frontend Flow (Edit)

1. **Fetch seller & store** using:
   - `GET /seller-users/{id}/details` (for compact view), and/or
   - `GET /seller-details/{id}` (for full details).
2. **Fetch onboarding progress**:
   - `GET /create-seller/progress?store_id={store_id}`.
3. **Edit Level 1**:
   - Submit to `POST /create-seller/level1/update`.
4. **Edit Level 2**:
   - Submit to `POST /create-seller/level2/update`.
5. **Edit Level 3**:
   - Submit to `POST /create-seller/level3/update`.
6. After each edit, refresh:
   - `GET /create-seller/progress?store_id={store_id}` to update progress UI.

