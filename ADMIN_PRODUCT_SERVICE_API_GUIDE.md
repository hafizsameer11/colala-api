# Admin Product & Service Management API Guide

## 🚀 Overview

New admin routes for creating products and services for any store, plus a store listing endpoint. These routes allow admins to manage products and services across all stores in the platform.

## 📋 API Endpoints

### **Base URL:** `https://your-api-domain.com/api/admin/`

---

## 🏪 Get All Stores

### **Get Stores List**
```http
GET /api/admin/stores
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "status": true,
  "message": "Stores retrieved successfully",
  "data": {
    "stores": [
      {
        "id": 1,
        "store_name": "Tech Store",
        "profile_image": "https://api.com/storage/stores/profile1.jpg",
        "banner_image": "https://api.com/storage/stores/banner1.jpg",
        "owner_name": "John Doe",
        "owner_email": "john@example.com"
      },
      {
        "id": 2,
        "store_name": "Fashion Hub",
        "profile_image": "https://api.com/storage/stores/profile2.jpg",
        "banner_image": null,
        "owner_name": "Jane Smith",
        "owner_email": "jane@example.com"
      }
    ],
    "total_stores": 2
  }
}
```

---

## 📦 Create Product (Admin)

### **Create Product for Any Store**
```http
POST /api/admin/products
Content-Type: multipart/form-data
Authorization: Bearer {admin_token}
```

**Form Data:**
```javascript
{
  "store_id": 1,                    // Required: Store ID
  "name": "iPhone 15 Pro",          // Required: Product name
  "description": "Latest iPhone...", // Required: Product description
  "price": 999.99,                  // Required: Product price
  "discount_price": 899.99,         // Optional: Discount price
  "category_id": 5,                 // Required: Category ID
  "quantity": 10,                   // Required: Stock quantity
  "images": [file1, file2, file3],  // Optional: Product images
  "video": {file},                  // Optional: Product video
  "variants": [                     // Optional: Product variants
    {
      "sku": "IPH15-128GB",
      "color": "Space Black",
      "size": "128GB",
      "price": 999.99,
      "discount_price": 899.99,
      "stock": 5,
      "images": [file1, file2]
    },
    {
      "sku": "IPH15-256GB",
      "color": "Space Black", 
      "size": "256GB",
      "price": 1099.99,
      "discount_price": 999.99,
      "stock": 3,
      "images": [file1, file2]
    }
  ]
}
```

**Response:**
```json
{
  "status": true,
  "message": "Product created successfully",
  "data": {
    "id": 123,
    "store_id": 1,
    "name": "iPhone 15 Pro",
    "description": "Latest iPhone...",
    "price": 999.99,
    "discount_price": 899.99,
    "category_id": 5,
    "quantity": 8,
    "video": "https://api.com/storage/products/videos/video123.mp4",
    "images": [
      {
        "id": 1,
        "path": "https://api.com/storage/products/image1.jpg",
        "is_main": true
      },
      {
        "id": 2,
        "path": "https://api.com/storage/products/image2.jpg",
        "is_main": false
      }
    ],
    "variants": [
      {
        "id": 1,
        "sku": "IPH15-128GB",
        "color": "Space Black",
        "size": "128GB",
        "price": 999.99,
        "discount_price": 899.99,
        "stock": 5,
        "images": [
          {
            "id": 3,
            "path": "https://api.com/storage/products/variant1.jpg"
          }
        ]
      }
    ],
    "store": {
      "id": 1,
      "store_name": "Tech Store",
      "profile_image": "https://api.com/storage/stores/profile1.jpg"
    },
    "category": {
      "id": 5,
      "name": "Electronics"
    }
  }
}
```

---

## 🛠️ Create Service (Admin)

### **Create Service for Any Store**
```http
POST /api/admin/services
Content-Type: multipart/form-data
Authorization: Bearer {admin_token}
```

**Form Data:**
```javascript
{
  "store_id": 2,                    // Required: Store ID
  "name": "Web Development",        // Required: Service name
  "short_description": "Professional web dev", // Optional: Short description
  "full_description": "Full-stack web development...", // Required: Full description
  "price_from": 500.00,            // Optional: Starting price
  "price_to": 2000.00,             // Optional: Maximum price
  "discount_price": 400.00,        // Optional: Discount price
  "category_id": 3,                // Required: Category ID
  "service_category_id": 7,        // Optional: Service category ID
  "status": "active",               // Optional: active, inactive, draft
  "video": {file},                  // Optional: Service video
  "media": [file1, file2, file3],  // Optional: Service media (images/videos)
  "sub_services": [                // Optional: Sub-services
    {
      "name": "Frontend Development",
      "price_from": 500.00,
      "price_to": 1000.00
    },
    {
      "name": "Backend Development", 
      "price_from": 800.00,
      "price_to": 1500.00
    },
    {
      "name": "Full-Stack Development",
      "price_from": 1200.00,
      "price_to": 2000.00
    }
  ]
}
```

**Response:**
```json
{
  "status": true,
  "message": "Service created successfully",
  "data": {
    "id": 456,
    "store_id": 2,
    "category_id": 3,
    "service_category_id": 7,
    "name": "Web Development",
    "short_description": "Professional web dev",
    "full_description": "Full-stack web development...",
    "price_from": 500.00,
    "price_to": 2000.00,
    "discount_price": 400.00,
    "status": "active",
    "video": "https://api.com/storage/services/videos/service123.mp4",
    "media": [
      {
        "id": 1,
        "type": "image",
        "path": "https://api.com/storage/services/image1.jpg"
      },
      {
        "id": 2,
        "type": "video",
        "path": "https://api.com/storage/services/video1.mp4"
      }
    ],
    "sub_services": [
      {
        "id": 1,
        "name": "Frontend Development",
        "price_from": 500.00,
        "price_to": 1000.00
      },
      {
        "id": 2,
        "name": "Backend Development",
        "price_from": 800.00,
        "price_to": 1500.00
      }
    ],
    "store": {
      "id": 2,
      "store_name": "Fashion Hub",
      "profile_image": "https://api.com/storage/stores/profile2.jpg"
    },
    "category": {
      "id": 3,
      "name": "Technology"
    },
    "service_category": {
      "id": 7,
      "name": "Web Development"
    }
  }
}
```

---

## 🔧 Validation Rules

### **Product Validation**
- `store_id`: Required, must exist in stores table
- `name`: Required, string, max 255 characters
- `description`: Required, string
- `price`: Required, numeric, min 0
- `discount_price`: Optional, numeric, min 0
- `category_id`: Required, must exist in categories table
- `quantity`: Required, integer, min 0
- `images`: Optional array, each file must be image (jpeg,png,jpg,gif), max 2MB
- `video`: Optional, must be video file (mp4,avi,mov), max 10MB
- `variants`: Optional array with variant validation

### **Service Validation**
- `store_id`: Required, must exist in stores table
- `name`: Required, string, max 255 characters
- `short_description`: Optional, string, max 500 characters
- `full_description`: Required, string
- `price_from`: Optional, numeric, min 0
- `price_to`: Optional, numeric, min 0
- `discount_price`: Optional, numeric, min 0
- `category_id`: Required, must exist in categories table
- `service_category_id`: Optional, must exist in service_categories table
- `status`: Optional, must be active, inactive, or draft
- `video`: Optional, must be video file (mp4,avi,mov), max 10MB
- `media`: Optional array, each file max 10MB
- `sub_services`: Optional array with sub-service validation

### **Variant Validation**
- `sku`: Optional, string, max 100 characters
- `color`: Optional, string, max 50 characters
- `size`: Optional, string, max 50 characters
- `price`: Optional, numeric, min 0
- `discount_price`: Optional, numeric, min 0
- `stock`: Optional, integer, min 0
- `images`: Optional array, each file must be image, max 2MB

### **Sub-Service Validation**
- `name`: Required, string, max 255 characters
- `price_from`: Optional, numeric, min 0
- `price_to`: Optional, numeric, min 0

---

## 📱 Frontend Integration Examples

### **JavaScript Fetch Examples**

#### **Get Stores List**
```javascript
const getStores = async () => {
  try {
    const response = await fetch('/api/admin/stores', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    return result.data.stores;
  } catch (error) {
    console.error('Error fetching stores:', error);
    throw error;
  }
};
```

#### **Create Product**
```javascript
const createProduct = async (formData) => {
  try {
    const response = await fetch('/api/admin/products', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
      },
      body: formData // FormData object with files
    });
    
    const result = await response.json();
    return result;
  } catch (error) {
    console.error('Error creating product:', error);
    throw error;
  }
};
```

#### **Create Service**
```javascript
const createService = async (formData) => {
  try {
    const response = await fetch('/api/admin/services', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
      },
      body: formData // FormData object with files
    });
    
    const result = await response.json();
    return result;
  } catch (error) {
    console.error('Error creating service:', error);
    throw error;
  }
};
```

### **React/Next.js Component Examples**

#### **Store Selector Component**
```jsx
import React, { useState, useEffect } from 'react';

const StoreSelector = ({ onStoreSelect, selectedStoreId }) => {
  const [stores, setStores] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchStores();
  }, []);

  const fetchStores = async () => {
    try {
      const response = await fetch('/api/admin/stores', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        }
      });
      const data = await response.json();
      setStores(data.data.stores);
    } catch (error) {
      console.error('Error fetching stores:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading stores...</div>;

  return (
    <div className="store-selector">
      <label className="block text-sm font-medium text-gray-700 mb-2">
        Select Store
      </label>
      <select
        value={selectedStoreId || ''}
        onChange={(e) => onStoreSelect(parseInt(e.target.value))}
        className="w-full border border-gray-300 rounded-md px-3 py-2"
      >
        <option value="">Choose a store...</option>
        {stores.map((store) => (
          <option key={store.id} value={store.id}>
            {store.store_name} - {store.owner_name}
          </option>
        ))}
      </select>
    </div>
  );
};

export default StoreSelector;
```

#### **Product Creation Form**
```jsx
import React, { useState } from 'react';

const ProductCreationForm = () => {
  const [formData, setFormData] = useState({
    store_id: '',
    name: '',
    description: '',
    price: '',
    discount_price: '',
    category_id: '',
    quantity: '',
    images: [],
    video: null,
    variants: []
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    const formDataToSend = new FormData();
    Object.keys(formData).forEach(key => {
      if (key === 'variants') {
        formDataToSend.append('variants', JSON.stringify(formData[key]));
      } else if (key === 'images' || key === 'video') {
        // Handle file uploads
        if (formData[key]) {
          if (Array.isArray(formData[key])) {
            formData[key].forEach(file => formDataToSend.append('images[]', file));
          } else {
            formDataToSend.append(key, formData[key]);
          }
        }
      } else {
        formDataToSend.append(key, formData[key]);
      }
    });

    try {
      const response = await fetch('/api/admin/products', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
        },
        body: formDataToSend
      });

      const result = await response.json();
      if (result.status) {
        alert('Product created successfully!');
        // Reset form or redirect
      }
    } catch (error) {
      console.error('Error creating product:', error);
      alert('Error creating product');
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Store Selection */}
      <StoreSelector 
        onStoreSelect={(storeId) => setFormData({...formData, store_id: storeId})}
        selectedStoreId={formData.store_id}
      />

      {/* Product Details */}
      <div>
        <label className="block text-sm font-medium text-gray-700">Product Name</label>
        <input
          type="text"
          value={formData.name}
          onChange={(e) => setFormData({...formData, name: e.target.value})}
          className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
          required
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">Description</label>
        <textarea
          value={formData.description}
          onChange={(e) => setFormData({...formData, description: e.target.value})}
          rows={4}
          className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
          required
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">Price</label>
          <input
            type="number"
            step="0.01"
            value={formData.price}
            onChange={(e) => setFormData({...formData, price: e.target.value})}
            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
            required
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Discount Price</label>
          <input
            type="number"
            step="0.01"
            value={formData.discount_price}
            onChange={(e) => setFormData({...formData, discount_price: e.target.value})}
            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
          />
        </div>
      </div>

      {/* File Uploads */}
      <div>
        <label className="block text-sm font-medium text-gray-700">Product Images</label>
        <input
          type="file"
          multiple
          accept="image/*"
          onChange={(e) => setFormData({...formData, images: Array.from(e.target.files)})}
          className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">Product Video</label>
        <input
          type="file"
          accept="video/*"
          onChange={(e) => setFormData({...formData, video: e.target.files[0]})}
          className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
        />
      </div>

      <button
        type="submit"
        className="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 transition-colors"
      >
        Create Product
      </button>
    </form>
  );
};

export default ProductCreationForm;
```

---

## 🎯 Key Features

### **Admin Capabilities**
- ✅ **Create products for any store** - Admins can add products to any store
- ✅ **Create services for any store** - Admins can add services to any store  
- ✅ **Store selection** - Get list of all stores with basic info
- ✅ **File uploads** - Support for images, videos, and media files
- ✅ **Variants support** - Products can have multiple variants with different prices/stock
- ✅ **Sub-services support** - Services can have multiple sub-services
- ✅ **Comprehensive validation** - Full validation for all fields and file types

### **Technical Features**
- ✅ **Database transactions** - All operations are wrapped in transactions
- ✅ **File storage** - Proper file storage with public URLs
- ✅ **Relationship loading** - Eager loading of related models
- ✅ **Error handling** - Comprehensive error handling and responses
- ✅ **Form data support** - Full multipart/form-data support for file uploads

This comprehensive admin API allows full management of products and services across all stores in the platform! 🚀
