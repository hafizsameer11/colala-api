# Seller Onboarding Field Keys Reference

This document provides all available field keys for the seller onboarding rejection system. Use these keys when calling the admin rejection endpoint.

## API Endpoint

**POST** `/admin/create-seller/reject-field`

**Request Body:**
```json
{
  "store_id": 123,
  "field_key": "level2.documents",
  "rejection_reason": "Document quality is too low. Please upload a clearer image."
}
```

## All Available Field Keys

### Level 1 Fields

| Field Key | Description | What Gets Rejected |
|-----------|-------------|-------------------|
| `level1.basic` | Basic store information | Store name, email, phone, location, referral code |
| `level1.profile_media` | Profile and banner images | Profile image and/or banner image |
| `level1.categories_social` | Categories and social links | Selected categories and/or social media links |

### Level 2 Fields

| Field Key | Description | What Gets Rejected |
|-----------|-------------|-------------------|
| `level2.business_details` | Business registration details | Registered name, business type, NIN number, BN number, CAC number |
| `level2.documents` | Business documents | NIN document, CAC document, utility bill, store video |

### Level 3 Fields

| Field Key | Description | What Gets Rejected |
|-----------|-------------|-------------------|
| `level3.physical_store` | Physical store information | Has physical store flag and/or store video |
| `level3.utility_bill` | Utility bill document | Utility bill upload |
| `level3.addresses` | Store addresses | One or more store addresses |
| `level3.delivery_pricing` | Delivery pricing configuration | Delivery pricing settings (Note: This field is excluded from progress calculation) |
| `level3.theme` | Store theme color | Theme color selection |

## Field Keys Array (for frontend use)

```javascript
const ONBOARDING_FIELD_KEYS = [
  // Level 1
  'level1.basic',
  'level1.profile_media',
  'level1.categories_social',
  
  // Level 2
  'level2.business_details',
  'level2.documents',
  
  // Level 3
  'level3.physical_store',
  'level3.utility_bill',
  'level3.addresses',
  'level3.delivery_pricing',
  'level3.theme'
];
```

## Field Keys with Labels (for UI display)

```javascript
const ONBOARDING_FIELDS = [
  {
    key: 'level1.basic',
    label: 'Basic Information',
    level: 1,
    description: 'Store name, email, phone, location'
  },
  {
    key: 'level1.profile_media',
    label: 'Profile & Banner Images',
    level: 1,
    description: 'Profile image and banner image'
  },
  {
    key: 'level1.categories_social',
    label: 'Categories & Social Links',
    level: 1,
    description: 'Selected categories and social media links'
  },
  {
    key: 'level2.business_details',
    label: 'Business Details',
    level: 2,
    description: 'Registered name, business type, NIN, BN, CAC numbers'
  },
  {
    key: 'level2.documents',
    label: 'Business Documents',
    level: 2,
    description: 'NIN document, CAC document, utility bill, store video'
  },
  {
    key: 'level3.physical_store',
    label: 'Physical Store Information',
    level: 3,
    description: 'Physical store flag and store video'
  },
  {
    key: 'level3.utility_bill',
    label: 'Utility Bill',
    level: 3,
    description: 'Utility bill document upload'
  },
  {
    key: 'level3.addresses',
    label: 'Store Addresses',
    level: 3,
    description: 'Store address information'
  },
  {
    key: 'level3.delivery_pricing',
    label: 'Delivery Pricing',
    level: 3,
    description: 'Delivery pricing configuration'
  },
  {
    key: 'level3.theme',
    label: 'Theme Color',
    level: 3,
    description: 'Store theme color selection'
  }
];
```

## TypeScript Interface (for TypeScript projects)

```typescript
type OnboardingFieldKey = 
  | 'level1.basic'
  | 'level1.profile_media'
  | 'level1.categories_social'
  | 'level2.business_details'
  | 'level2.documents'
  | 'level3.physical_store'
  | 'level3.utility_bill'
  | 'level3.addresses'
  | 'level3.delivery_pricing'
  | 'level3.theme';

interface OnboardingField {
  key: OnboardingFieldKey;
  label: string;
  level: 1 | 2 | 3;
  description: string;
}

interface RejectFieldRequest {
  store_id: number;
  field_key: OnboardingFieldKey;
  rejection_reason: string; // Max 1000 characters
}

interface RejectFieldResponse {
  status: boolean;
  message: string;
  data: {
    store_id: number;
    field_key: OnboardingFieldKey;
    status: 'rejected';
    rejection_reason: string;
    progress: {
      level: number;
      percent: number;
      status: string;
    };
  };
}
```

## Example Usage

### React Component Example

```jsx
import React, { useState } from 'react';

const ONBOARDING_FIELDS = [
  { key: 'level1.basic', label: 'Basic Information', level: 1 },
  { key: 'level1.profile_media', label: 'Profile & Banner Images', level: 1 },
  { key: 'level1.categories_social', label: 'Categories & Social Links', level: 1 },
  { key: 'level2.business_details', label: 'Business Details', level: 2 },
  { key: 'level2.documents', label: 'Business Documents', level: 2 },
  { key: 'level3.physical_store', label: 'Physical Store Information', level: 3 },
  { key: 'level3.utility_bill', label: 'Utility Bill', level: 3 },
  { key: 'level3.addresses', label: 'Store Addresses', level: 3 },
  { key: 'level3.delivery_pricing', label: 'Delivery Pricing', level: 3 },
  { key: 'level3.theme', label: 'Theme Color', level: 3 },
];

function RejectFieldModal({ storeId, onClose }) {
  const [selectedField, setSelectedField] = useState('');
  const [rejectionReason, setRejectionReason] = useState('');

  const handleReject = async () => {
    try {
      const response = await fetch('/api/admin/create-seller/reject-field', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          store_id: storeId,
          field_key: selectedField,
          rejection_reason: rejectionReason
        })
      });
      
      const data = await response.json();
      if (data.status) {
        alert('Field rejected successfully');
        onClose();
      }
    } catch (error) {
      console.error('Error rejecting field:', error);
    }
  };

  return (
    <div className="modal">
      <h2>Reject Onboarding Field</h2>
      
      <label>Select Field:</label>
      <select 
        value={selectedField} 
        onChange={(e) => setSelectedField(e.target.value)}
      >
        <option value="">-- Select Field --</option>
        {ONBOARDING_FIELDS.map(field => (
          <option key={field.key} value={field.key}>
            Level {field.level}: {field.label}
          </option>
        ))}
      </select>

      <label>Rejection Reason:</label>
      <textarea
        value={rejectionReason}
        onChange={(e) => setRejectionReason(e.target.value)}
        placeholder="Enter reason for rejection..."
        maxLength={1000}
        rows={4}
      />

      <button onClick={handleReject}>Reject Field</button>
      <button onClick={onClose}>Cancel</button>
    </div>
  );
}
```

## Notes

1. **Rejection Reason**: Maximum 1000 characters
2. **Field Status**: When a field is rejected, its status changes to `'rejected'` and the `rejection_reason` is stored
3. **Re-upload**: When a seller re-uploads a rejected field, the rejection is automatically cleared
4. **Progress Calculation**: Rejected fields are excluded from the "done" count in progress calculation
5. **Delivery Pricing**: The `level3.delivery_pricing` field is excluded from progress calculation in the seller's progress endpoint

## Response Format

When checking progress, rejected fields will include:
```json
{
  "key": "level2.documents",
  "status": "rejected",
  "completed_at": null,
  "rejection_reason": "Document quality is too low. Please upload a clearer image.",
  "is_rejected": true
}
```

