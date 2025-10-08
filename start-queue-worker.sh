#!/bin/bash

# Start Laravel Queue Worker for Bulk Upload Processing
# This script starts the queue worker with appropriate settings for bulk upload jobs

echo "Starting Laravel Queue Worker for Bulk Upload Processing..."
echo "=========================================================="

# Check if artisan exists
if [ ! -f "artisan" ]; then
    echo "Error: artisan file not found. Please run this script from the Laravel project root."
    exit 1
fi

# Check if database connection is working
echo "Testing database connection..."
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection: OK';" 2>/dev/null

if [ $? -ne 0 ]; then
    echo "Error: Database connection failed. Please check your database configuration."
    exit 1
fi

echo "Database connection: OK"
echo ""

# Start queue worker with appropriate settings
echo "Starting queue worker with the following settings:"
echo "- Timeout: 300 seconds (5 minutes)"
echo "- Memory limit: 512MB"
echo "- Max tries: 3"
echo "- Sleep: 3 seconds"
echo ""

# Start the worker
php artisan queue:work \
    --timeout=300 \
    --memory=512 \
    --tries=3 \
    --sleep=3 \
    --verbose

echo ""
echo "Queue worker stopped."
