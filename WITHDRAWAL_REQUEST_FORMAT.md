# Automatic Withdrawal Request Format

## 📋 Request Format for `POST /api/wallet/withdraw/auto`

After successful account validation, use the following format to submit the withdrawal:

### **Request Body:**

```json
{
  "bank_code": "100004",
  "bank_name": "Access Bank",
  "account_number": "9063939859",
  "account_name": "OCHE PETER ODOH",
  "amount": 5000
}
```

### **Field Mapping from Validation Response:**

| Field | Source | Example |
|-------|--------|---------|
| `bank_code` | From validation response `data.bank_code` | `"100004"` |
| `bank_name` | From bank list (when user selected bank) | `"Access Bank"` |
| `account_number` | From validation response `data.account_number` | `"9063939859"` |
| `account_name` | From validation response `data.account_name` | `"OCHE PETER ODOH"` |
| `amount` | User input (minimum 100) | `5000` |

### **Complete Frontend Flow:**

```javascript
// Step 1: Get banks
const banksResponse = await fetch('/api/wallet/withdraw/banks');
const { data: banks } = await banksResponse.json();
// banks = [{ id: 1, code: "100004", name: "Access Bank" }, ...]

// Step 2: Validate account
const validateResponse = await fetch('/api/wallet/withdraw/validate-account', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    bank_code: selectedBank.code,  // "100004"
    account_number: accountNumber   // "9063939859"
  })
});

const validationResult = await validateResponse.json();
// validationResult.data = {
//   account_name: "OCHE PETER ODOH",
//   account_number: "9063939859",
//   bank_code: "100004",
//   valid: true
// }

// Step 3: Submit withdrawal
const withdrawResponse = await fetch('/api/wallet/withdraw/auto', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    bank_code: validationResult.data.bank_code,        // "100004"
    bank_name: selectedBank.name,                      // "Access Bank" (from bank list)
    account_number: validationResult.data.account_number, // "9063939859"
    account_name: validationResult.data.account_name,    // "OCHE PETER ODOH"
    amount: amount                                     // 5000 (user input)
  })
});
```

### **Validation Rules:**

- `bank_code`: Required, must be numeric string (e.g., "100004", "044")
- `bank_name`: Required, string (e.g., "Access Bank")
- `account_number`: Required, 10-12 digits numeric string
- `account_name`: Required, string (from validation)
- `amount`: Required, numeric, minimum 100 NGN

---

## 📦 Where Webhook JSON is Saved

The complete webhook payload is saved in the `withdrawal_requests` table in the `webhook_data` JSON column.

### **Database Location:**
- **Table:** `withdrawal_requests`
- **Column:** `webhook_data` (JSON type)
- **Model:** `App\Models\WithdrawalRequest`

### **What Gets Saved:**

When Flutterwave sends a webhook, the **complete request payload** is saved:

```json
{
  "event": "transfer.completed",
  "data": {
    "id": 123456,
    "account_number": "9063939859",
    "bank_code": "100004",
    "amount": 5000,
    "currency": "NGN",
    "reference": "payout-550e8400-e29b-41d4-a716-446655440000",
    "status": "SUCCESSFUL",
    "complete_message": "Transfer completed successfully",
    "created_at": "2025-11-21T14:30:00.000Z",
    "updated_at": "2025-11-21T14:30:05.000Z"
  }
}
```

### **How to Access:**

```php
$withdrawal = WithdrawalRequest::find($id);
$webhookData = $withdrawal->webhook_data; // Returns array (automatically cast)

// Access specific fields
$transferId = $webhookData['data']['id'] ?? null;
$status = $webhookData['data']['status'] ?? null;
```

### **When It's Saved:**

The webhook data is saved in `WebhookController::flutterwave()` method at **line 34**:

```php
$payout->webhook_data = $request->all(); // Saves complete webhook payload
$payout->save();
```

This happens automatically when Flutterwave sends the webhook after a transfer is completed.

