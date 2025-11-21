# Flutterwave Automatic Withdrawal Integration Guide

## 📋 Overview

This guide explains how the Flutterwave automatic payout system works and the frontend integration flow.

## 🔄 Complete Flow

### **Frontend Flow (3 Steps)**

```
┌─────────────────────────────────────────────────────────────┐
│                    STEP 1: Get Bank List                    │
│  GET /api/wallet/withdraw/banks                             │
│  → User sees dropdown with all Nigerian banks               │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│              STEP 2: Validate Account Number                 │
│  POST /api/wallet/withdraw/validate-account                 │
│  Body: { bank_code, account_number }                         │
│  → Backend validates and returns account_name               │
│  → Frontend shows: "Account Name: John Doe"                 │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│              STEP 3: Submit Withdrawal Request               │
│  POST /api/wallet/withdraw/auto                             │
│  Body: { bank_code, bank_name, account_number,              │
│         account_name, amount }                               │
│  → Backend processes automatic payout                       │
│  → Returns reference and status                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔌 API Endpoints

### **1. Get Banks List**
**Endpoint:** `GET /api/wallet/withdraw/banks`

**Query Parameters:**
- `country` (optional): Default is `NG` for Nigeria

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 302,
      "code": "057",
      "name": "Zenith Bank"
    },
    {
      "id": 303,
      "code": "058",
      "name": "GTBank"
    }
    // ... more banks
  ]
}
```

**Frontend Usage:**
```javascript
// Fetch banks for dropdown
const response = await fetch('/api/wallet/withdraw/banks?country=NG');
const { data } = await response.json();
// Populate dropdown with data array
```

---

### **2. Validate Account Number**
**Endpoint:** `POST /api/wallet/withdraw/validate-account`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "bank_code": "057",
  "account_number": "1234567890"
}
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Account validated successfully",
  "data": {
    "account_name": "JOHN DOE",
    "account_number": "1234567890",
    "bank_code": "057",
    "valid": true
  }
}
```

**Error Response (422):**
```json
{
  "status": "error",
  "message": "Invalid bank account details"
}
```

**Frontend Usage:**
```javascript
// Validate account when user enters account number
const validateAccount = async (bankCode, accountNumber) => {
  const response = await fetch('/api/wallet/withdraw/validate-account', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      bank_code: bankCode,
      account_number: accountNumber
    })
  });
  
  const result = await response.json();
  
  if (result.status === 'success') {
    // Show account name to user
    setAccountName(result.data.account_name);
    setValidated(true);
  } else {
    // Show error message
    setError(result.message);
  }
};
```

---

### **3. Submit Automatic Withdrawal**
**Endpoint:** `POST /api/wallet/withdraw/auto`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "bank_code": "057",
  "bank_name": "Zenith Bank",
  "account_number": "1234567890",
  "account_name": "JOHN DOE",
  "amount": 5000
}
```

**Validation Rules:**
- `bank_code`: Required, string
- `bank_name`: Required, string
- `account_number`: Required, string, 10-12 digits
- `account_name`: Required, string (from validation step)
- `amount`: Required, numeric, minimum 100 NGN

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Withdrawal initiated successfully",
  "data": {
    "withdrawal": {
      "id": 1,
      "user_id": 123,
      "bank_code": "057",
      "bank_name": "Zenith Bank",
      "account_number": "1234567890",
      "account_name": "JOHN DOE",
      "amount": "5000.00",
      "reference": "payout-550e8400-e29b-41d4-a716-446655440000",
      "status": "pending",
      "created_at": "2025-11-21T14:30:00.000000Z"
    },
    "reference": "payout-550e8400-e29b-41d4-a716-446655440000",
    "flutterwave_response": {
      "status": "success",
      "message": "Transfer queued",
      "data": {
        "id": 123456,
        "account_number": "1234567890",
        "bank_code": "057",
        "amount": 5000,
        "currency": "NGN",
        "reference": "payout-550e8400-e29b-41d4-a716-446655440000"
      }
    }
  }
}
```

**Error Responses:**

**Insufficient Balance (422):**
```json
{
  "status": "error",
  "message": "Insufficient wallet balance."
}
```

**Invalid Account (422):**
```json
{
  "status": "error",
  "message": "Invalid bank account details"
}
```

**Frontend Usage:**
```javascript
// Submit withdrawal after validation
const submitWithdrawal = async (formData) => {
  const response = await fetch('/api/wallet/withdraw/auto', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      bank_code: formData.bankCode,
      bank_name: formData.bankName,
      account_number: formData.accountNumber,
      account_name: formData.accountName, // From validation step
      amount: formData.amount
    })
  });
  
  const result = await response.json();
  
  if (result.status === 'success') {
    // Show success message
    // Redirect to withdrawal history
    // Show reference number
  } else {
    // Show error message
  }
};
```

---

## 🔔 Webhook Endpoint

**Endpoint:** `POST /api/flutterwave/webhook`

**Purpose:** Flutterwave automatically calls this when transfer status changes.

**Headers (from Flutterwave):**
```
verif-hash: {your_webhook_secret}
```

**Webhook Payload:**
```json
{
  "event": "transfer.completed",
  "data": {
    "id": 123456,
    "reference": "payout-550e8400-e29b-41d4-a716-446655440000",
    "status": "SUCCESSFUL",
    "complete_message": "Transfer completed successfully"
  }
}
```

**What Happens:**
1. Webhook verifies signature
2. Finds withdrawal by `reference`
3. Updates status:
   - `SUCCESSFUL` → `approved`
   - Other statuses → `rejected`
4. Saves Flutterwave transfer ID and remarks

**Note:** This is automatic - no frontend action needed.

---

## 📱 Frontend Implementation Example

### **React/Flutter/Vue Flow:**

```javascript
// Step 1: Load banks on component mount
useEffect(() => {
  fetchBanks();
}, []);

const fetchBanks = async () => {
  const response = await fetch('/api/wallet/withdraw/banks');
  const { data } = await response.json();
  setBanks(data);
};

// Step 2: Validate when user enters account number
const handleAccountNumberChange = async (accountNumber) => {
  if (accountNumber.length >= 10 && selectedBank) {
    setValidating(true);
    const response = await fetch('/api/wallet/withdraw/validate-account', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        bank_code: selectedBank.code,
        account_number: accountNumber
      })
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
      setAccountName(result.data.account_name);
      setAccountValidated(true);
      setError(null);
    } else {
      setError(result.message);
      setAccountValidated(false);
    }
    setValidating(false);
  }
};

// Step 3: Submit withdrawal
const handleSubmit = async () => {
  if (!accountValidated) {
    setError('Please validate account number first');
    return;
  }
  
  const response = await fetch('/api/wallet/withdraw/auto', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      bank_code: selectedBank.code,
      bank_name: selectedBank.name,
      account_number: accountNumber,
      account_name: accountName,
      amount: amount
    })
  });
  
  const result = await response.json();
  
  if (result.status === 'success') {
    // Show success
    navigate('/withdrawals');
  } else {
    setError(result.message);
  }
};
```

---

## 🎯 UI Flow Recommendation

### **Screen 1: Bank Selection**
```
┌─────────────────────────────────────┐
│  Select Bank                        │
│  ┌─────────────────────────────┐   │
│  │ [Dropdown: Select Bank ▼]   │   │
│  └─────────────────────────────┘   │
│                                      │
│  Enter Account Number                │
│  ┌─────────────────────────────┐   │
│  │ [1234567890]                │   │
│  └─────────────────────────────┘   │
│                                      │
│  [Validate Account]                  │
└─────────────────────────────────────┘
```

### **Screen 2: Account Validated**
```
┌─────────────────────────────────────┐
│  ✓ Account Validated                 │
│                                      │
│  Account Name: JOHN DOE              │
│  Bank: Zenith Bank                   │
│  Account: 1234567890                 │
│                                      │
│  Enter Amount                        │
│  ┌─────────────────────────────┐   │
│  │ [5000]                      │   │
│  └─────────────────────────────┘   │
│                                      │
│  Available Balance: ₦50,000          │
│                                      │
│  [Confirm Withdrawal]                │
└─────────────────────────────────────┘
```

### **Screen 3: Success**
```
┌─────────────────────────────────────┐
│  ✓ Withdrawal Initiated              │
│                                      │
│  Reference: payout-xxx-xxx           │
│  Amount: ₦5,000                      │
│  Status: Pending                     │
│                                      │
│  You will receive the funds shortly  │
│                                      │
│  [View Withdrawal History]           │
└─────────────────────────────────────┘
```

---

## 🔒 Security Features

1. **Account Validation:** Account is validated before withdrawal
2. **Webhook Verification:** Signature verification prevents fake webhooks
3. **Balance Check:** Insufficient balance prevents withdrawal
4. **Transaction Logging:** All transactions are logged
5. **Unique References:** Each withdrawal has unique reference for tracking

---

## 📊 Status Flow

```
pending → (Flutterwave processes) → approved/rejected
```

**Status Values:**
- `pending`: Withdrawal initiated, waiting for Flutterwave
- `approved`: Transfer successful (from webhook)
- `rejected`: Transfer failed (from webhook)

---

## 🛠️ Environment Variables

Add to `.env`:
```env
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST_xxxxx
FLUTTERWAVE_WEBHOOK_SECRET=your_webhook_hash_from_dashboard
```

**Get Webhook Secret:**
1. Login to Flutterwave Dashboard
2. Go to Settings → Webhooks
3. Copy the "Secret Hash" value

---

## ✅ Testing Checklist

- [ ] Get banks list works
- [ ] Account validation works with valid account
- [ ] Account validation fails with invalid account
- [ ] Withdrawal succeeds with sufficient balance
- [ ] Withdrawal fails with insufficient balance
- [ ] Webhook updates status correctly
- [ ] Reference numbers are unique
- [ ] Transaction records are created

---

## 🐛 Troubleshooting

**Issue: "Invalid bank account details"**
- Check bank_code matches selected bank
- Verify account_number is 10-12 digits
- Ensure account exists in that bank

**Issue: "Insufficient wallet balance"**
- Check user's shopping_balance
- Minimum withdrawal is 100 NGN

**Issue: Webhook not updating status**
- Verify FLUTTERWAVE_WEBHOOK_SECRET matches dashboard
- Check webhook URL is correct in Flutterwave dashboard
- Check server logs for webhook errors

---

## 📞 Support

For Flutterwave API issues, check:
- [Flutterwave API Docs](https://developer.flutterwave.com/docs)
- [Flutterwave Support](https://support.flutterwave.com)

