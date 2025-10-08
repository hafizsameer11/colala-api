# Database Queue Setup Guide

## Overview
This system uses Laravel's database queue driver instead of Redis, making it simpler to set up and manage.

## Prerequisites
- ✅ Laravel application
- ✅ MySQL database
- ✅ Jobs table migration (already exists)

## Configuration

### 1. Environment Variables
Add to your `.env` file:
```env
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE_RETRY_AFTER=90
```

### 2. Database Tables
The system uses two main tables:

#### `jobs` table (Laravel default)
```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL
);
```

#### `bulk_upload_jobs` table (Custom)
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
    updated_at TIMESTAMP
);
```

## Queue Worker Setup

### 1. Start Queue Worker
```bash
# Basic queue worker
php artisan queue:work

# With specific options
php artisan queue:work --timeout=300 --memory=512 --tries=3

# Process specific queue
php artisan queue:work --queue=default
```

### 2. Production Setup with Supervisor

#### Install Supervisor
```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

#### Supervisor Configuration
Create `/etc/supervisor/conf.d/laravel-worker.conf`:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

#### Start Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 3. Development Setup

#### Manual Worker (Development)
```bash
# Start worker in terminal
php artisan queue:work

# Or with specific options
php artisan queue:work --timeout=300 --memory=512
```

#### Background Process (Development)
```bash
# Using nohup
nohup php artisan queue:work > storage/logs/queue.log 2>&1 &

# Using screen
screen -S queue-worker
php artisan queue:work
# Press Ctrl+A, then D to detach
```

## Monitoring

### 1. Check Queue Status
```bash
# Check pending jobs
php artisan queue:work --once

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### 2. Database Monitoring
```sql
-- Check pending jobs
SELECT COUNT(*) FROM jobs WHERE reserved_at IS NULL;

-- Check processing jobs
SELECT COUNT(*) FROM jobs WHERE reserved_at IS NOT NULL;

-- Check bulk upload jobs
SELECT status, COUNT(*) FROM bulk_upload_jobs GROUP BY status;
```

### 3. Log Monitoring
```bash
# Monitor queue logs
tail -f storage/logs/laravel.log

# Monitor worker logs
tail -f storage/logs/worker.log
```

## Troubleshooting

### Common Issues

#### 1. Jobs Not Processing
```bash
# Check if worker is running
ps aux | grep "queue:work"

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### 2. Memory Issues
```bash
# Increase memory limit
php artisan queue:work --memory=1024

# Or set in php.ini
memory_limit = 512M
```

#### 3. Timeout Issues
```bash
# Increase timeout
php artisan queue:work --timeout=600

# Check job timeout in job class
public $timeout = 300; // 5 minutes
```

#### 4. Database Connection Issues
```bash
# Test database connection
php artisan tinker
>>> DB::table('jobs')->count();

# Check queue configuration
php artisan config:show queue
```

### Debug Commands

#### Check Queue Configuration
```bash
php artisan config:show queue
```

#### Test Job Dispatch
```bash
php artisan tinker
>>> App\Jobs\ProcessBulkProductUpload::dispatch(1, [], 'test');
```

#### Monitor Job Processing
```bash
# Watch jobs table
watch -n 1 "mysql -u username -p database -e 'SELECT COUNT(*) FROM jobs;'"

# Watch bulk upload jobs
watch -n 1 "mysql -u username -p database -e 'SELECT status, COUNT(*) FROM bulk_upload_jobs GROUP BY status;'"
```

## Performance Optimization

### 1. Database Optimization
```sql
-- Add indexes for better performance
ALTER TABLE jobs ADD INDEX idx_queue_reserved (queue, reserved_at);
ALTER TABLE jobs ADD INDEX idx_available (available_at);
ALTER TABLE bulk_upload_jobs ADD INDEX idx_user_status (user_id, status);
```

### 2. Worker Optimization
```bash
# Run multiple workers
php artisan queue:work --timeout=300 &
php artisan queue:work --timeout=300 &
php artisan queue:work --timeout=300 &
```

### 3. Memory Management
```bash
# Monitor memory usage
ps aux | grep "queue:work"

# Restart workers periodically
# Add to cron: */30 * * * * pkill -f "queue:work" && php artisan queue:work --daemon
```

## Security Considerations

### 1. File Permissions
```bash
# Ensure proper permissions
chmod 755 storage/logs/
chown www-data:www-data storage/logs/
```

### 2. Database Security
- Use dedicated database user for queue operations
- Limit database user permissions
- Enable SSL for database connections

### 3. Log Security
```bash
# Secure log files
chmod 640 storage/logs/*.log
chown www-data:www-data storage/logs/*.log
```

## Backup and Recovery

### 1. Database Backup
```bash
# Backup jobs table
mysqldump -u username -p database jobs > jobs_backup.sql

# Backup bulk upload jobs
mysqldump -u username -p database bulk_upload_jobs > bulk_upload_jobs_backup.sql
```

### 2. Job Recovery
```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry 123
```

This database queue setup is simple, reliable, and doesn't require Redis! 🚀
