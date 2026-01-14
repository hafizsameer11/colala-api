# Apple In-App Purchase (IAP) Integration - Backend Implementation

## Overview
This document describes the backend implementation for Apple In-App Purchase subscriptions. The implementation allows iOS users to purchase subscriptions through Apple's payment system, with automatic renewal handling via webhooks.

## Database Changes

### 1. Subscription Plans Table
Added two new columns to `subscription_plans`:
- `apple_product_id_monthly` (VARCHAR 255, nullable)
- `apple_product_id_annual` (VARCHAR 255, nullable)

**Migration:** `2026_01_14_170736_add_apple_iap_fields_to_subscription_plans_table.php`

### 2. Subscriptions Table
Added new columns to `subscriptions`:
- `apple_transaction_id` (VARCHAR 255, nullable)
- `apple_original_transaction_id` (VARCHAR 255, nullable)
- `apple_receipt_data` (TEXT, nullable)
- `is_auto_renewable` (BOOLEAN, default false)

**Updated:** `payment_method` enum now includes `'apple_iap'`

**Migration:** `2026_01_14_170741_add_apple_iap_fields_to_subscriptions_table.php`

## Services Created

### 1. AppleReceiptValidationService
**Location:** `app/Services/AppleReceiptValidationService.php`

**Methods:**
- `validateReceipt(string $receiptData, bool $isSandbox = false): array` - Validates receipt with Apple
- `extractSubscriptionInfo(array $receiptData): array` - Extracts subscription details from Apple response
- `isTransactionNew(string $transactionId): bool` - Checks if transaction was already used

**Features:**
- Handles both sandbox and production environments
- Automatically retries with sandbox if production returns status 21007
- Extracts transaction IDs, expiry dates, and auto-renewal status

### 2. AppleWebhookService
**Location:** `app/Services/AppleWebhookService.php`

**Methods:**
- `handleNotification(array $notificationPayload): bool` - Main handler for Apple notifications

**Notification Types Handled:**
- `INITIAL_BUY` - First purchase (logged for tracking)
- `DID_RENEW` - **Most Important** - Handles subscription renewal, extends end_date
- `DID_FAIL_TO_RENEW` - Payment failed, logs for monitoring
- `DID_CANCEL` - User cancelled, sets `is_auto_renewable = false`
- `EXPIRED` - Subscription expired, updates status
- `GRACE_PERIOD_EXPIRED` - Grace period ended
- `REFUND` - Refund issued, cancels subscription

## API Endpoints

### 1. GET /api/seller/plans
**Updated** to include Apple product IDs in response:
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "Basic",
      "price": 5000,
      "apple_product_id_monthly": "com.colala.basic.monthly",
      "apple_product_id_annual": "com.colala.basic.annual",
      ...
    }
  ]
}
```

### 2. POST /api/seller/subscriptions
**Updated** to handle `payment_method: "apple_iap"`

**Request Body (for Apple IAP):**
```json
{
  "plan_id": 1,
  "payment_method": "apple_iap",
  "receipt_data": "base64_encoded_receipt",
  "transaction_id": "1000000123456789",
  "original_transaction_id": "1000000123456789",
  "product_id": "com.colala.basic.monthly",
  "billing_period": "monthly"
}
```

### 3. POST /api/seller/subscriptions/validate-receipt
**New Endpoint** - Validates Apple receipt and activates subscription

**Request Body:**
```json
{
  "receipt_data": "base64_encoded_receipt_string",
  "transaction_id": "1000000123456789",
  "original_transaction_id": "1000000123456789",
  "plan_id": 1,
  "billing_period": "monthly",
  "product_id": "com.colala.basic.monthly"
}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "subscription": {
      "id": 123,
      "plan_id": 1,
      "status": "active",
      "start_date": "2026-01-10",
      "end_date": "2026-02-10",
      "payment_method": "apple_iap",
      "is_auto_renewable": true,
      "apple_transaction_id": "1000000123456789",
      "apple_original_transaction_id": "1000000123456789"
    }
  }
}
```

### 4. POST /api/seller/subscriptions/apple-webhook
**New Endpoint** - Handles Apple server-to-server notifications

**Route:** Public (no authentication required) - `routes/api.php`

**Purpose:** Receives JWT-signed notifications from Apple for:
- Subscription renewals (`DID_RENEW`)
- Cancellations (`DID_CANCEL`)
- Expirations (`EXPIRED`)
- Refunds (`REFUND`)
- And other notification types

**Response:** Always returns `200 OK` to acknowledge receipt

### 5. POST /api/seller/subscriptions/restore-purchases
**New Endpoint** - Restores previous Apple IAP purchases

**Request Body:**
```json
{
  "receipt_data": "base64_encoded_receipt_string"
}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "subscriptions": [
      {
        "id": 123,
        "plan_id": 1,
        "status": "active",
        "start_date": "2026-01-10",
        "end_date": "2026-02-10"
      }
    ]
  }
}
```

## Configuration

### Environment Variables
Add to your `.env` file:

```env
# Apple IAP Configuration
APPLE_SHARED_SECRET=your_shared_secret_from_app_store_connect
APPLE_USE_SANDBOX=true  # Set to false for production
```

**Note:** The shared secret is optional but recommended for auto-renewable subscriptions. Get it from App Store Connect → Your App → App Information → App-Specific Shared Secret.

## Setup Instructions

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Update Subscription Plans
Add Apple product IDs to your subscription plans:

```sql
UPDATE subscription_plans 
SET 
  apple_product_id_monthly = 'com.colala.basic.monthly',
  apple_product_id_annual = 'com.colala.basic.annual'
WHERE id = 1;

UPDATE subscription_plans 
SET 
  apple_product_id_monthly = 'com.colala.pro.monthly',
  apple_product_id_annual = 'com.colala.pro.annual'
WHERE id = 2;
```

### 3. Configure App Store Connect
1. Go to App Store Connect → Your App → App Information
2. Set "Server Notification URL" to: `https://your-api.com/api/seller/subscriptions/apple-webhook`
3. Configure your subscription products with matching product IDs

### 4. Testing
- Use Apple Sandbox accounts for testing
- Set `APPLE_USE_SANDBOX=true` in `.env` for sandbox testing
- Test receipt validation with sandbox receipts
- Test webhook notifications using Apple's test notifications

## Important Notes

### Product IDs
- Product IDs must match **exactly** between App Store Connect and your database
- Format: `com.colala.{plan_name}.{period}` (e.g., `com.colala.basic.monthly`)

### Receipt Validation
- Backend **must** validate receipts server-side (Apple requirement)
- Receipts are validated with Apple's App Store Server API
- Both sandbox and production environments are supported

### Auto-Renewal
- Apple handles payment processing automatically
- Backend receives `DID_RENEW` notification when subscription renews
- Backend extends `end_date` and updates subscription status
- Your existing renewal logic runs when webhook is received

### Webhook Security
- Currently, JWT payload is decoded without signature verification
- **For production**, implement proper JWT signature verification using Apple's public keys
- See Apple's documentation for JWT verification: https://developer.apple.com/documentation/appstoreserverapi/verifying_transaction_and_subscription_status_update_notifications

### Transaction Deduplication
- Backend checks if `transaction_id` was already used before creating subscription
- Prevents duplicate activations from the same transaction

## Files Modified/Created

### Migrations
- `database/migrations/2026_01_14_170736_add_apple_iap_fields_to_subscription_plans_table.php`
- `database/migrations/2026_01_14_170741_add_apple_iap_fields_to_subscriptions_table.php`

### Models
- `app/Models/SubscriptionPlan.php` - Added fillable fields
- `app/Models/Subscription.php` - Added fillable fields and casts

### Services
- `app/Services/AppleReceiptValidationService.php` - **NEW**
- `app/Services/AppleWebhookService.php` - **NEW**

### Controllers
- `app/Http/Controllers/Api/SubscriptionController.php` - Added new methods

### Resources
- `app/Http/Resources/SubscriptionPlanResource.php` - Added product IDs

### Requests
- `app/Http/Requests/SubscriptionRequest.php` - Added Apple IAP validation

### Routes
- `routes/seller.php` - Added validate-receipt and restore-purchases routes
- `routes/api.php` - Added apple-webhook route (public)

### Config
- `config/services.php` - Added Apple configuration

## Next Steps

1. **Run migrations** to add database columns
2. **Update subscription plans** with Apple product IDs
3. **Set environment variables** for Apple configuration
4. **Configure webhook URL** in App Store Connect
5. **Test with sandbox** before going to production
6. **Implement JWT verification** for production webhook security (recommended)

## Testing Checklist

- [ ] Receipt validation works with sandbox receipts
- [ ] Receipt validation works with production receipts
- [ ] Webhook receives and processes `DID_RENEW` notifications
- [ ] Subscription renewals extend `end_date` correctly
- [ ] Cancellations set `is_auto_renewable = false`
- [ ] Expirations update status to `expired`
- [ ] Restore purchases returns correct subscriptions
- [ ] Duplicate transactions are prevented
- [ ] Product ID validation works correctly

## Support

For issues or questions:
1. Check Apple's App Store Server API documentation
2. Review webhook logs in `storage/logs/laravel.log`
3. Test with Apple's sandbox environment first

