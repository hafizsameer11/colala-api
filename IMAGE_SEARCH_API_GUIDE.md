# Image Search API Guide

## Overview
The Image Search API allows users to upload an image and find visually similar products using CLIP embeddings and cosine similarity.

## Endpoint
```
POST /api/search/by-image
```

## Request Format
- **Content-Type**: `multipart/form-data`
- **Authentication**: Not required (public endpoint)

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `image` | File | Yes | Image file (max 10MB) |
| `top` | Integer | No | Number of results to return (1-50, default: 10) |

### Supported Image Formats
- JPEG
- PNG
- GIF
- WebP
- BMP

## Response Format

### Success Response (200)
```json
{
  "status": "success",
  "query_image_url": "https://example.com/storage/temp/search/image123.jpg",
  "count": 5,
  "results": [
    {
      "score": 0.854321,
      "product": {
        "id": 123,
        "name": "Product Name",
        "price": 29.99,
        "discount_price": 24.99,
        "store_id": 45,
        "category_id": 12,
        "average_rating": 4.5,
        "image_url": "https://example.com/storage/products/main.jpg"
      }
    }
  ]
}
```

### Error Responses

#### Validation Error (422)
```json
{
  "status": "error",
  "message": "The image field is required."
}
```

#### Image Not Publicly Accessible (422)
```json
{
  "status": "error",
  "message": "Image is not publicly accessible. Set APP_URL to a public domain."
}
```

#### Embedding Generation Failed (500)
```json
{
  "status": "error",
  "message": "Failed to generate a valid embedding for the image."
}
```

#### Replicate API Error (500)
```json
{
  "status": "error",
  "message": "Replicate: insufficient credit (402)"
}
```

## Environment Configuration

### Required Environment Variables
```env
# Replicate API Token
REPLICATE_API_TOKEN=your_replicate_token_here

# Public App URL (for image accessibility)
APP_URL=https://yourdomain.com
```

### Optional Environment Variables
```env
# No additional configuration required
```

## How It Works

1. **Image Upload**: User uploads an image via multipart form data
2. **Temporary Storage**: Image is stored temporarily in `storage/app/public/temp/search/`
3. **Public URL Generation**: A public URL is generated for the image using APP_URL
4. **Embedding Generation**: The image URL is sent to Replicate's CLIP API to generate a 512-dimensional embedding
5. **Similarity Search**: The embedding is compared against all stored product embeddings using cosine similarity
6. **Results Ranking**: Products are ranked by similarity score and top N results are returned
7. **Cleanup**: Temporary files are deleted

## Technical Details

### Embedding Generation
- Uses Replicate's OpenAI CLIP model
- Generates 512-dimensional embeddings
- Synchronous processing with `Prefer: wait` header
- 2-minute timeout for API calls

### Similarity Calculation
- Cosine similarity between query and product embeddings
- Scores range from -1 to 1 (higher is more similar)
- Chunked processing for large datasets (2000 records per chunk)

### Performance Considerations
- Database queries are chunked to avoid memory issues
- Temporary files are automatically cleaned up
- Results are limited to 50 products maximum
- Logging is comprehensive for debugging

## Usage Examples

### cURL Example
```bash
curl -X POST https://yourdomain.com/api/search/by-image \
  -H "Accept: application/json" \
  -F "image=@/path/to/your/image.jpg" \
  -F "top=8"
```

### JavaScript Example
```javascript
const formData = new FormData();
formData.append('image', fileInput.files[0]);
formData.append('top', '10');

fetch('/api/search/by-image', {
  method: 'POST',
  body: formData
})
.then(response => response.json())
.then(data => {
  console.log('Search results:', data.results);
});
```

### PHP Example
```php
$response = Http::attach('image', file_get_contents('image.jpg'), 'image.jpg')
    ->post('https://yourdomain.com/api/search/by-image', [
        'top' => 5
    ]);

$results = $response->json();
```

## Logging

All operations are logged to `storage/logs/replicate_embeddings.log` with detailed information:

- Image upload and URL generation
- Cloudinary upload attempts
- Replicate API calls and responses
- Similarity calculations
- Error handling and debugging

## Error Handling

The API handles various error scenarios:

1. **File Validation**: Invalid file types or sizes
2. **Public URL Issues**: Ensure APP_URL is set to a public domain
3. **Replicate API Errors**: Rate limits, insufficient credits, timeouts
4. **Database Issues**: Missing embeddings, connection problems
5. **Memory Issues**: Large datasets, chunked processing

## Rate Limits

- Replicate API: Subject to your Replicate account limits
- No built-in rate limiting on the endpoint
- Consider implementing rate limiting for production use

## Security Considerations

- File upload validation prevents malicious files
- Temporary files are automatically cleaned up
- No authentication required (public endpoint)
- Consider adding rate limiting for production

## Monitoring

Monitor the following for optimal performance:

- Replicate API usage and costs
- Database query performance
- File storage usage
- Error rates in logs
- Response times

## Troubleshooting

### Common Issues

1. **"Image not publicly accessible"**
   - Set `APP_URL` to a public domain

2. **"Failed to generate embedding"**
   - Check `REPLICATE_API_TOKEN`
   - Verify image format and size
   - Check Replicate account credits

3. **"No results found"**
   - Ensure product embeddings exist in database
   - Check if products have valid images
   - Verify similarity threshold

4. **Slow performance**
   - Monitor database query times
   - Consider indexing on embedding columns
   - Check Replicate API response times

### Debug Commands

```bash
# Check logs
tail -f storage/logs/replicate_embeddings.log

# Test Replicate connection
php artisan replicate:index-products --test

# Check embedding count
php artisan tinker
>>> App\Models\ProductEmbedding::count()
```
