# Date Filter Implementation Guide for Frontend

## Overview
All admin list endpoints now support **three methods** of date filtering with the following priority:
1. **Period** (highest priority) - Predefined time ranges
2. **Custom Date Range** (`date_from` & `date_to`) - User-selected date range
3. **Legacy Date Range** (`date_range`) - Old format (for backward compatibility)

## Filter Priority
When multiple date filters are provided, the backend applies them in this order:
1. If `period` is provided → Uses period filter (ignores date_from/date_to)
2. Else if `date_from` AND `date_to` are provided → Uses custom date range
3. Else if `date_range` is provided → Uses legacy date_range (backward compatibility)

## API Parameters

### 1. Period Filter
**Parameter:** `period`  
**Type:** String  
**Valid Values:**
- `today` - Today's data
- `this_week` - Current week (Monday to Sunday)
- `this_month` - Current month
- `last_month` - Previous month
- `this_year` - Current year
- `all_time` - All data (no filter)
- `null` - All data (no filter)

**Example:**
```
GET /api/admin/products?period=this_month&page=1
GET /api/admin/orders?period=today&status=completed
```

### 2. Custom Date Range Filter
**Parameters:** `date_from` & `date_to`  
**Type:** String (Date format: `Y-m-d` or `YYYY-MM-DD`)  
**Format:** `2025-01-15` (ISO date format)

**Important Notes:**
- Both `date_from` AND `date_to` must be provided together
- If only one is provided, it will be ignored
- Date format: `YYYY-MM-DD` (e.g., `2025-01-15`)
- `date_from` is inclusive (start of day)
- `date_to` is inclusive (end of day)

**Example:**
```
GET /api/admin/products?date_from=2025-01-01&date_to=2025-01-31&page=1
GET /api/admin/orders?date_from=2025-12-01&date_to=2025-12-31&status=pending
```

### 3. Legacy Date Range (Backward Compatibility)
**Parameter:** `date_range`  
**Type:** String  
**Valid Values:** `today`, `this_week`, `this_month`, `all`

**Note:** This is for backward compatibility. Prefer using `period` instead.

## Frontend Implementation Examples

### React/TypeScript Example

```typescript
// Date filter state
interface DateFilterState {
  filterType: 'period' | 'custom' | 'none';
  period: string | null;
  dateFrom: string | null;
  dateTo: string | null;
}

// Component state
const [dateFilter, setDateFilter] = useState<DateFilterState>({
  filterType: 'none',
  period: null,
  dateFrom: null,
  dateTo: null,
});

// Build query parameters
const buildQueryParams = (filters: DateFilterState, otherParams: Record<string, any> = {}) => {
  const params = new URLSearchParams();
  
  // Add other filters first
  Object.entries(otherParams).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, String(value));
    }
  });
  
  // Add date filter based on priority
  if (filters.filterType === 'period' && filters.period) {
    params.append('period', filters.period);
  } else if (filters.filterType === 'custom' && filters.dateFrom && filters.dateTo) {
    params.append('date_from', filters.dateFrom);
    params.append('date_to', filters.dateTo);
  }
  
  return params.toString();
};

// Usage in API call
const fetchProducts = async () => {
  const queryString = buildQueryParams(dateFilter, {
    page: currentPage,
    status: selectedStatus,
    search: searchTerm,
  });
  
  const response = await fetch(`/api/admin/products?${queryString}`);
  return response.json();
};
```

### Vue.js Example

```vue
<template>
  <div>
    <!-- Period Filter -->
    <select v-model="dateFilter.filterType" @change="onFilterTypeChange">
      <option value="none">No Date Filter</option>
      <option value="period">Quick Period</option>
      <option value="custom">Custom Range</option>
    </select>
    
    <!-- Period Selector -->
    <select v-if="dateFilter.filterType === 'period'" v-model="dateFilter.period">
      <option value="today">Today</option>
      <option value="this_week">This Week</option>
      <option value="this_month">This Month</option>
      <option value="last_month">Last Month</option>
      <option value="this_year">This Year</option>
      <option value="all_time">All Time</option>
    </select>
    
    <!-- Custom Date Range -->
    <div v-if="dateFilter.filterType === 'custom'">
      <input 
        type="date" 
        v-model="dateFilter.dateFrom" 
        placeholder="Start Date"
      />
      <input 
        type="date" 
        v-model="dateFilter.dateTo" 
        placeholder="End Date"
      />
    </div>
    
    <button @click="fetchData">Apply Filter</button>
  </div>
</template>

<script>
export default {
  data() {
    return {
      dateFilter: {
        filterType: 'none',
        period: null,
        dateFrom: null,
        dateTo: null,
      }
    };
  },
  methods: {
    buildQueryParams() {
      const params = new URLSearchParams();
      
      if (this.dateFilter.filterType === 'period' && this.dateFilter.period) {
        params.append('period', this.dateFilter.period);
      } else if (this.dateFilter.filterType === 'custom' && 
                 this.dateFilter.dateFrom && 
                 this.dateFilter.dateTo) {
        params.append('date_from', this.dateFilter.dateFrom);
        params.append('date_to', this.dateFilter.dateTo);
      }
      
      return params.toString();
    },
    async fetchData() {
      const queryString = this.buildQueryParams();
      const response = await fetch(`/api/admin/products?${queryString}`);
      return response.json();
    }
  }
};
</script>
```

### JavaScript (Vanilla) Example

```javascript
// Date filter configuration
const dateFilter = {
  filterType: 'none', // 'period', 'custom', or 'none'
  period: null,
  dateFrom: null,
  dateTo: null
};

// Build query string
function buildQueryString(filters, additionalParams = {}) {
  const params = new URLSearchParams();
  
  // Add additional parameters
  Object.entries(additionalParams).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, value);
    }
  });
  
  // Add date filter (priority: period > custom)
  if (filters.filterType === 'period' && filters.period) {
    params.append('period', filters.period);
  } else if (filters.filterType === 'custom' && filters.dateFrom && filters.dateTo) {
    params.append('date_from', filters.dateFrom);
    params.append('date_to', filters.dateTo);
  }
  
  return params.toString();
}

// Example API call
async function fetchAdminData(endpoint, filters, page = 1) {
  const queryString = buildQueryString(filters, { page });
  const response = await fetch(`${endpoint}?${queryString}`);
  return response.json();
}

// Usage
const filters = {
  filterType: 'custom',
  dateFrom: '2025-01-01',
  dateTo: '2025-01-31'
};

fetchAdminData('/api/admin/products', filters, 1);
```

## UI/UX Recommendations

### 1. Filter Selection UI
```
┌─────────────────────────────────────┐
│ Date Filter: [Dropdown]             │
│ ┌─────────────────────────────────┐ │
│ │ ○ No Filter                     │ │
│ │ ● Quick Period                  │ │
│ │ ○ Custom Range                  │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

### 2. Quick Period Selector
When "Quick Period" is selected:
```
┌─────────────────────────────────────┐
│ Period: [Dropdown ▼]                │
│ ┌─────────────────────────────────┐ │
│ │ Today                           │ │
│ │ This Week                       │ │
│ │ This Month                      │ │
│ │ Last Month                      │ │
│ │ This Year                       │ │
│ │ All Time                        │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

### 3. Custom Date Range Picker
When "Custom Range" is selected:
```
┌─────────────────────────────────────┐
│ From: [📅 2025-01-01]               │
│ To:   [📅 2025-01-31]               │
│                                      │
│ [Clear] [Apply]                     │
└─────────────────────────────────────┘
```

## Complete Example: React Component

```tsx
import React, { useState, useEffect } from 'react';
import axios from 'axios';

interface DateFilterProps {
  onFilterChange: (params: Record<string, any>) => void;
}

const DateFilterComponent: React.FC<DateFilterProps> = ({ onFilterChange }) => {
  const [filterType, setFilterType] = useState<'none' | 'period' | 'custom'>('none');
  const [period, setPeriod] = useState<string>('all_time');
  const [dateFrom, setDateFrom] = useState<string>('');
  const [dateTo, setDateTo] = useState<string>('');

  useEffect(() => {
    const params: Record<string, any> = {};
    
    if (filterType === 'period' && period) {
      params.period = period;
    } else if (filterType === 'custom' && dateFrom && dateTo) {
      params.date_from = dateFrom;
      params.date_to = dateTo;
    }
    
    onFilterChange(params);
  }, [filterType, period, dateFrom, dateTo, onFilterChange]);

  return (
    <div className="date-filter">
      <label>
        Filter Type:
        <select 
          value={filterType} 
          onChange={(e) => setFilterType(e.target.value as any)}
        >
          <option value="none">No Filter</option>
          <option value="period">Quick Period</option>
          <option value="custom">Custom Range</option>
        </select>
      </label>

      {filterType === 'period' && (
        <label>
          Period:
          <select value={period} onChange={(e) => setPeriod(e.target.value)}>
            <option value="today">Today</option>
            <option value="this_week">This Week</option>
            <option value="this_month">This Month</option>
            <option value="last_month">Last Month</option>
            <option value="this_year">This Year</option>
            <option value="all_time">All Time</option>
          </select>
        </label>
      )}

      {filterType === 'custom' && (
        <>
          <label>
            From:
            <input 
              type="date" 
              value={dateFrom} 
              onChange={(e) => setDateFrom(e.target.value)}
            />
          </label>
          <label>
            To:
            <input 
              type="date" 
              value={dateTo} 
              onChange={(e) => setDateTo(e.target.value)}
            />
          </label>
        </>
      )}
    </div>
  );
};

// Usage in parent component
const AdminProductsPage = () => {
  const [filters, setFilters] = useState<Record<string, any>>({});
  const [products, setProducts] = useState([]);

  const fetchProducts = async () => {
    const params = new URLSearchParams({
      page: '1',
      ...filters
    });
    
    const response = await axios.get(`/api/admin/products?${params}`);
    setProducts(response.data.data.products);
  };

  useEffect(() => {
    fetchProducts();
  }, [filters]);

  return (
    <div>
      <DateFilterComponent onFilterChange={setFilters} />
      {/* Products list */}
    </div>
  );
};
```

## Important Notes

1. **Date Format:** Always use `YYYY-MM-DD` format (e.g., `2025-01-15`)
2. **Both Required:** For custom date range, both `date_from` and `date_to` must be provided
3. **Priority:** If `period` is provided, `date_from` and `date_to` will be ignored
4. **Validation:** Backend validates date formats and ensures `date_from <= date_to`
5. **Time Zones:** Dates are handled in server timezone (UTC)

## Supported Endpoints

All admin list endpoints support these date filters:
- `/api/admin/products`
- `/api/admin/services`
- `/api/admin/orders`
- `/api/admin/buyer-orders`
- `/api/admin/transactions`
- `/api/admin/buyer-transactions`
- `/api/admin/users`
- `/api/admin/seller-users`
- `/api/admin/support/tickets`
- `/api/admin/subscriptions`
- `/api/admin/notifications`
- `/api/admin/chats`
- And all other admin list endpoints using `PeriodFilterTrait`

## Testing Examples

```bash
# Using period
curl "https://api.colalamall.com/api/admin/products?period=this_month&page=1"

# Using custom date range
curl "https://api.colalamall.com/api/admin/products?date_from=2025-01-01&date_to=2025-01-31&page=1"

# Combining with other filters
curl "https://api.colalamall.com/api/admin/orders?period=this_week&status=completed&search=order123"

# Custom date range with search
curl "https://api.colalamall.com/api/admin/products?date_from=2025-01-01&date_to=2025-01-31&status=active&search=laptop"
```

