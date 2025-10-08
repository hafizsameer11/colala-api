# Bulk Product Upload Guide

## Overview
The bulk product upload feature allows sellers to upload multiple products at once using CSV files with Google Drive links for images and videos.

## Features
- ✅ CSV-based bulk product upload
- ✅ Google Drive integration for media files
- ✅ Product variants support
- ✅ Image and video download from Google Drive
- ✅ Comprehensive error handling
- ✅ Progress tracking and detailed results

## API Endpoints

### 1. Get CSV Template
```
GET /api/seller/products/bulk-upload/template
```
Returns the CSV structure and sample data.

### 2. Get Categories
```
GET /api/seller/products/bulk-upload/categories
```
Returns available categories for reference.

### 3. Upload CSV Data (JSON)
```
POST /api/seller/products/bulk-upload
Content-Type: application/json

{
  "csv_data": [
    {
      "name": "Product Name",
      "category_id": "1",
      "brand": "Brand Name",
      "description": "Product description",
      "price": "99.99",
      "discount_price": "79.99",
      "has_variants": "true",
      "status": "active",
      "loyality_points_applicable": "true",
      "video_url": "https://drive.google.com/file/d/1ABC123XYZ/view",
      "main_image_url": "https://drive.google.com/file/d/1DEF456UVW/view",
      "image_urls": "https://drive.google.com/file/d/1GHI789RST/view,https://drive.google.com/file/d/1JKL012MNO/view",
      "variants_data": "[{\"sku\":\"VAR001\",\"color\":\"Red\",\"size\":\"M\",\"price\":99.99,\"discount_price\":79.99,\"stock\":10,\"image_urls\":\"https://drive.google.com/file/d/1PQR345STU/view\"}]"
    }
  ]
}
```

### 4. Upload CSV File
```
POST /api/seller/products/bulk-upload/file
Content-Type: multipart/form-data

csv_file: [CSV file]
```

## CSV Format

### Required Fields
| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Product name (required) |
| `category_id` | integer | Category ID from categories table (required) |
| `description` | string | Product description (required) |
| `price` | decimal | Base price (required) |

### Optional Fields
| Field | Type | Description |
|-------|------|-------------|
| `brand` | string | Product brand |
| `discount_price` | decimal | Discounted price |
| `has_variants` | boolean | Whether product has variants (true/false) |
| `status` | string | Product status (active/inactive) |
| `loyality_points_applicable` | boolean | Whether loyalty points apply (true/false) |
| `video_url` | string | Google Drive video URL |
| `main_image_url` | string | Google Drive main image URL |
| `image_urls` | string | Comma-separated Google Drive image URLs |
| `variants_data` | string | JSON string with variant data |

### Variants Data Format
The `variants_data` field should contain a JSON string with variant information:

```json
[
  {
    "sku": "VAR001",
    "color": "Red",
    "size": "M",
    "price": 99.99,
    "discount_price": 79.99,
    "stock": 10,
    "image_urls": "https://drive.google.com/file/d/1PQR345STU/view"
  },
  {
    "sku": "VAR002",
    "color": "Blue",
    "size": "L",
    "price": 99.99,
    "discount_price": 79.99,
    "stock": 15,
    "image_urls": "https://drive.google.com/file/d/1VWX678YZA/view"
  }
]
```

## Google Drive URL Formats

The system supports various Google Drive URL formats:
- `https://drive.google.com/file/d/FILE_ID/view`
- `https://drive.google.com/open?id=FILE_ID`
- `https://drive.google.com/uc?id=FILE_ID`

## Response Format

### Success Response
```json
{
  "status": true,
  "message": "Bulk upload processed successfully",
  "data": {
    "success": [
      {
        "row": 1,
        "product_id": 123,
        "product_name": "Sample Product",
        "message": "Product created successfully"
      }
    ],
    "errors": [
      {
        "row": 2,
        "error": "Invalid category_id",
        "data": {...}
      }
    ],
    "total_processed": 2,
    "success_count": 1,
    "error_count": 1
  }
}
```

## Error Handling

The system provides detailed error information:
- **Validation Errors**: Field validation failures
- **Google Drive Errors**: Download failures
- **Database Errors**: Creation failures
- **File Format Errors**: Invalid CSV structure

## Best Practices

### 1. CSV Preparation
- Use UTF-8 encoding
- Include headers in first row
- Use proper escaping for special characters
- Test with small batches first

### 2. Google Drive Setup
- Ensure files are publicly accessible
- Use direct download links
- Test links before uploading
- Keep file sizes reasonable (< 50MB)

### 3. Data Validation
- Verify category IDs exist
- Check price formats
- Validate JSON for variants
- Test with sample data first

## Sample CSV

```csv
name,category_id,brand,description,price,discount_price,has_variants,status,loyality_points_applicable,video_url,main_image_url,image_urls,variants_data
"Sample T-Shirt",1,"Nike","High quality cotton t-shirt",29.99,24.99,true,active,true,"https://drive.google.com/file/d/1ABC123XYZ/view","https://drive.google.com/file/d/1DEF456UVW/view","https://drive.google.com/file/d/1GHI789RST/view","[{\"sku\":\"TSH001\",\"color\":\"Red\",\"size\":\"M\",\"price\":29.99,\"stock\":10}]"
```

## Technical Implementation

### Services
- `GoogleDriveService`: Handles Google Drive downloads
- `BulkProductUploadService`: Processes CSV data and creates products

### Features
- Automatic file download from Google Drive
- Image and video processing
- Variant creation with images
- Quantity calculation
- Transaction safety
- Comprehensive error reporting

## Limitations
- Maximum file size: 10MB
- Google Drive rate limits apply
- Network timeout: 60 seconds per file
- Supported formats: CSV, TXT

## Support
For issues or questions:
1. Check error messages in response
2. Verify Google Drive URLs are accessible
3. Ensure CSV format matches template
4. Test with small data sets first
