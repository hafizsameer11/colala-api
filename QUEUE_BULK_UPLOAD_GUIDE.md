# Queue-Based Bulk Product Upload System

## Overview
The bulk product upload system now uses Laravel queues for asynchronous processing, making it suitable for large CSV files with Google Drive downloads that can take significant time.

## Key Features
- ✅ **Asynchronous Processing**: Jobs run in background queues
- ✅ **Progress Tracking**: Real-time status updates
- ✅ **Google Drive Integration**: Automatic file downloads
- ✅ **Error Handling**: Comprehensive error reporting
- ✅ **Job Management**: Track multiple upload jobs
- ✅ **Results Storage**: Detailed success/error reports

## Architecture

### Components
1. **ProcessBulkProductUpload Job**: Handles the actual processing
2. **BulkUploadJob Model**: Tracks job status and results
3. **BulkProductUploadService**: Manages job creation and status
4. **BulkProductUploadController**: API endpoints for job management

### Queue Configuration
The system uses database queues (no Redis required). Make sure your queue is configured in `.env`:
```env
QUEUE_CONNECTION=database
```

## API Endpoints

### 1. Upload CSV Data
```http
POST /api/seller/products/bulk-upload
Content-Type: application/json

{
  "csv_data": [
    {
      "name": "Product Name",
      "category_id": "1",
      "description": "Product description",
      "price": "99.99",
      "video_url": "https://drive.google.com/file/d/1ABC123XYZ/view"
    }
  ]
}
```

**Response:**
```json
{
  "status": true,
  "message": "Bulk upload job has been queued",
  "data": {
    "upload_id": "bulk_abc123xyz789",
    "status": "pending",
    "message": "Bulk upload job has been queued and will be processed in the background",
    "total_rows": 1
  }
}
```

### 2. Upload CSV File
```http
POST /api/seller/products/bulk-upload/file
Content-Type: multipart/form-data

csv_file: [CSV file]
```

### 3. Check Job Status
```http
GET /api/seller/products/bulk-upload/jobs/{uploadId}/status
```

**Response:**
```json
{
  "status": true,
  "data": {
    "upload_id": "bulk_abc123xyz789",
    "status": "processing",
    "progress_percentage": 45,
    "total_rows": 100,
    "processed_rows": 45,
    "success_count": 40,
    "error_count": 5,
    "started_at": "2025-01-07T10:30:00Z",
    "completed_at": null,
    "error_message": null,
    "results": null
  }
}
```

### 4. Get Job Results
```http
GET /api/seller/products/bulk-upload/jobs/{uploadId}/results
```

**Response:**
```json
{
  "status": true,
  "data": {
    "upload_id": "bulk_abc123xyz789",
    "status": "completed",
    "total_rows": 100,
    "success_count": 95,
    "error_count": 5,
    "results": {
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
      ]
    },
    "completed_at": "2025-01-07T10:35:00Z"
  }
}
```

### 5. Get All User Jobs
```http
GET /api/seller/products/bulk-upload/jobs
```

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "upload_id": "bulk_abc123xyz789",
      "status": "completed",
      "progress_percentage": 100,
      "total_rows": 100,
      "processed_rows": 100,
      "success_count": 95,
      "error_count": 5,
      "started_at": "2025-01-07T10:30:00Z",
      "completed_at": "2025-01-07T10:35:00Z",
      "created_at": "2025-01-07T10:29:00Z"
    }
  ]
}
```

## Job Statuses

| Status | Description |
|--------|-------------|
| `pending` | Job is queued but not started |
| `processing` | Job is currently running |
| `completed` | Job finished successfully |
| `failed` | Job failed with errors |

## Database Schema

### bulk_upload_jobs Table
```sql
CREATE TABLE bulk_upload_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    upload_id VARCHAR(255) UNIQUE,
    user_id BIGINT UNSIGNED,
    status ENUM('pending', 'processing', 'completed', 'failed'),
    csv_data JSON,
    results JSON,
    error_message TEXT,
    total_rows INT DEFAULT 0,
    processed_rows INT DEFAULT 0,
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Queue Processing

### Start Queue Worker
```bash
# Process jobs from database queue
php artisan queue:work

# Process jobs with specific timeout
php artisan queue:work --timeout=300

# Process jobs with memory limit
php artisan queue:work --memory=512
```

### Monitor Queue
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

## Job Configuration

### Timeout Settings
- **Job Timeout**: 300 seconds (5 minutes)
- **Max Tries**: 3 attempts
- **Queue**: Default queue

### Memory Management
- Jobs process one product at a time
- Google Drive downloads are streamed
- Images are stored immediately after download
- Database transactions ensure data integrity

## Error Handling

### Job Failures
- Automatic retry (up to 3 times)
- Detailed error logging
- Status updates in database
- User notification via API

### Common Issues
1. **Google Drive Access**: Ensure URLs are publicly accessible
2. **File Size**: Large files may timeout
3. **Network Issues**: Automatic retry mechanism
4. **Database Locks**: Transaction-based processing

## Performance Considerations

### Queue Workers
- Run multiple workers for parallel processing
- Monitor memory usage
- Set appropriate timeouts
- Use supervisor for production

### Google Drive Downloads
- Files are downloaded sequentially
- Each download has 60-second timeout
- Failed downloads are logged and skipped
- Progress is tracked per product

### Database Optimization
- Bulk operations where possible
- Indexed foreign keys
- JSON storage for results
- Efficient status updates

## Production Setup

### Supervisor Configuration
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/worker.log
stopwaitsecs=3600
```

### Environment Variables
```env
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE_RETRY_AFTER=90
```

## Monitoring

### Job Status Tracking
- Real-time progress updates
- Success/error counts
- Processing time tracking
- Detailed error messages

### Logging
- All job activities are logged
- Google Drive download logs
- Database operation logs
- Error stack traces

## Best Practices

### CSV Preparation
- Test with small batches first
- Validate Google Drive URLs
- Use proper CSV encoding
- Include error handling

### Queue Management
- Monitor queue length
- Set up alerts for failures
- Regular cleanup of old jobs
- Backup job data

### Google Drive Setup
- Use direct download links
- Ensure public access
- Test file accessibility
- Monitor download speeds

## Troubleshooting

### Common Issues
1. **Jobs not processing**: Check queue worker status
2. **Google Drive failures**: Verify URL accessibility
3. **Memory issues**: Increase worker memory limit
4. **Timeout errors**: Adjust job timeout settings

### Debug Commands
```bash
# Check queue status
php artisan queue:work --once

# View job details
php artisan tinker
>>> App\Models\BulkUploadJob::find('upload_id')

# Monitor logs
tail -f storage/logs/laravel.log
```

This queue-based system ensures reliable processing of large bulk uploads while providing real-time feedback to users! 🚀
