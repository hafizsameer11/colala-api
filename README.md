<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Colala API

## Google Cloud Vision Product Search Setup

This project includes Google Cloud Vision Product Search integration for visual product search capabilities.

### Dependencies

Install the following packages:

```bash
composer require google/cloud-vision:^2.1 google/cloud-storage:^1.12
```

### Environment Configuration

Add the following environment variables to your `.env` file:

```env
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google/service-account.json
GOOGLE_CLOUD_PROJECT_ID=your_project_id
GOOGLE_CLOUD_LOCATION=us-west1
GOOGLE_CLOUD_STORAGE_BUCKET=your_bucket_name
QUEUE_CONNECTION=database
```

### Google Cloud Setup

1. **Create a Google Cloud Project** and enable the Vision API
2. **Create a Service Account** with the following IAM roles:
   - `roles/vision.productSearchEditor`
   - `roles/storage.objectAdmin`
   - `roles/serviceusage.serviceUsageConsumer`
3. **Download the service account JSON** and save it to `storage/app/google/service-account.json`
4. **Create a Google Cloud Storage bucket** in the same region as your Vision API location
5. **Set up budget alerts** in Google Cloud Console to monitor costs

### Database Setup

Run the migration to add Vision columns:

```bash
php artisan migrate
```

### Queue Setup

Create the jobs table for queue processing:

```bash
php artisan queue:table
php artisan migrate
```

### Backfill Existing Data

To index all existing products and images:

```bash
php artisan vision:backfill
```

### Start Queue Worker

Run the queue worker to process Vision indexing jobs:

```bash
php artisan queue:work --queue=vision,default
```

### API Endpoints

- `POST /api/search/image-exact` - Search for visually similar products by uploading an image

### Features

- **Automatic Indexing**: Products and images are automatically indexed when created/updated
- **Background Processing**: All Vision operations run in background jobs with retry logic
- **Error Handling**: Failed operations are tracked with error messages in the database
- **Backfill Support**: Command to index all existing data in chunks
- **Visual Search**: API endpoint for image-based product search with score thresholds
- **Regional API**: Uses regional endpoints for better performance and compliance
