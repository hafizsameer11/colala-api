# Replicate CLIP Embeddings Implementation Guide

This implementation provides a complete solution for indexing product images with CLIP embeddings using Replicate's API.

## 🏗️ Architecture Overview

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Command       │───▶│      Job         │───▶│    Service      │
│ replicate:index │    │ IndexOldProduct  │    │ ReplicateEmbed  │
│ -products       │    │ ImagesJob        │    │ dingService     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │
                                ▼
                       ┌─────────────────┐
                       │ ProductEmbedding│
                       │     Model       │
                       └─────────────────┘
```

## 📁 Files Created

### 1. Migration
- `database/migrations/2025_01_15_030000_create_product_embeddings_table.php`
- Creates `product_embeddings` table with foreign keys and unique constraints

### 2. Model
- `app/Models/ProductEmbedding.php`
- Handles embedding storage with JSON casting
- Relationships to Product and ProductImage

### 3. Service
- `app/Services/ReplicateEmbeddingService.php`
- Handles HTTP calls to Replicate API
- Error logging to custom log file
- Connection testing

### 4. Job
- `app/Jobs/IndexOldProductImagesJob.php`
- Background processing with retry logic
- Exponential backoff (1min, 2min, 5min)
- Custom logging to `replicate_embeddings.log`

### 5. Command
- `app/Console/Commands/ReplicateIndexProducts.php`
- CLI interface for dispatching jobs
- Progress tracking and API testing

## 🚀 Usage

### 1. Environment Setup
Add to your `.env` file:
```bash
REPLICATE_API_TOKEN=your_replicate_api_token_here
```

### 2. Run Migration
```bash
php artisan migrate
```

### 3. Test API Connection
```bash
php artisan replicate:index-products --test
```

### 4. Index All Product Images
```bash
# Basic usage
php artisan replicate:index-products

# With custom options
php artisan replicate:index-products --chunk=100 --queue=embeddings

# Test mode (no jobs dispatched)
php artisan replicate:index-products --test
```

### 5. Process Jobs
```bash
# Start queue worker
php artisan queue:work --queue=embeddings

# Or use default queue
php artisan queue:work
```

## 📊 Monitoring

### Log Files
- **Progress**: `storage/logs/replicate_embeddings.log`
- **Laravel**: `storage/logs/laravel.log`

### Log Format
```
[2025-01-15 10:30:45] Indexed ProductImage #34 for Product #12
[2025-01-15 10:30:46] Failed ProductImage #35 - error: API request failed with status 429
```

### Database Queries
```sql
-- Check embedding progress
SELECT 
    COUNT(*) as total_embeddings,
    COUNT(DISTINCT product_id) as unique_products
FROM product_embeddings;

-- Find products without embeddings
SELECT p.id, p.name 
FROM products p 
LEFT JOIN product_embeddings pe ON p.id = pe.product_id 
WHERE pe.id IS NULL;
```

## ⚙️ Configuration Options

### Command Options
- `--chunk=50`: Process images in batches of 50
- `--queue=default`: Use specific queue name
- `--test`: Test API without dispatching jobs

### Job Configuration
- **Retries**: 3 attempts maximum
- **Backoff**: 60s, 120s, 300s (exponential)
- **Timeout**: 120 seconds per API call
- **Queue**: Configurable via command option

## 🔧 API Integration

### Replicate API Details
- **Endpoint**: `POST https://api.replicate.com/v1/models/openai/clip/predictions`
- **Headers**: 
  - `Authorization: Bearer {token}`
  - `Content-Type: application/json`
  - `Prefer: wait`
- **Body**: `{"input": {"image": "https://example.com/image.jpg"}}`
- **Response**: `{"output": [{"embedding": [0.1, 0.2, ...]}]}`

### Error Handling
- Network timeouts (120s)
- API rate limits (429 errors)
- Invalid image URLs
- Malformed responses
- Authentication failures

## 📈 Performance Considerations

### Batch Processing
- Default chunk size: 50 images
- Memory efficient for large datasets
- Progress tracking with progress bar

### Queue Management
- Jobs are queued, not processed immediately
- Use `queue:work` to process background jobs
- Monitor queue status with `queue:monitor`

### Rate Limiting
- Built-in retry logic with exponential backoff
- Handles Replicate API rate limits gracefully
- Custom error logging for debugging

## 🛠️ Troubleshooting

### Common Issues

1. **API Token Not Set**
   ```
   Error: REPLICATE_API_TOKEN environment variable is not set
   ```
   **Solution**: Add token to `.env` file

2. **Queue Not Processing**
   ```
   Jobs are queued but not processing
   ```
   **Solution**: Start queue worker with `php artisan queue:work`

3. **Image URLs Invalid**
   ```
   Failed ProductImage #123 - Invalid image path
   ```
   **Solution**: Check image storage configuration

4. **API Rate Limits**
   ```
   API request failed with status 429
   ```
   **Solution**: Jobs will retry automatically with backoff

### Debug Commands
```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Check log file
tail -f storage/logs/replicate_embeddings.log
```

## 🔍 Advanced Usage

### Custom Queue Processing
```bash
# Process specific queue
php artisan queue:work --queue=embeddings

# Process with specific connection
php artisan queue:work --connection=redis --queue=embeddings
```

### Monitoring with Supervisor
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=embeddings --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

## 📋 Implementation Checklist

- [x] Migration for `product_embeddings` table
- [x] `ProductEmbedding` model with relationships
- [x] `ReplicateEmbeddingService` for API calls
- [x] `IndexOldProductImagesJob` with retry logic
- [x] `replicate:index-products` command
- [x] Custom logging to `replicate_embeddings.log`
- [x] Error handling and retry mechanisms
- [x] Progress tracking and monitoring
- [x] Documentation and usage guide

## 🎯 Next Steps

1. **Run Migration**: `php artisan migrate`
2. **Set API Token**: Add `REPLICATE_API_TOKEN` to `.env`
3. **Test Connection**: `php artisan replicate:index-products --test`
4. **Start Processing**: `php artisan replicate:index-products`
5. **Monitor Progress**: `tail -f storage/logs/replicate_embeddings.log`
6. **Process Jobs**: `php artisan queue:work`

The implementation is now ready for production use! 🚀
