# Simple Store User Management System

## 🎯 **Simplified Approach: One User = One Store**

### **Core Concept**
- **One user can belong to ONE store only**
- **One store can have MULTIPLE users**
- **Store owner is automatically assigned during registration**
- **Additional users can be added to the store**

## 🏗️ **Database Schema**

### **Users Table Update**
```sql
ALTER TABLE users ADD COLUMN store_id BIGINT UNSIGNED NULL;
ALTER TABLE users ADD FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL;
```

### **Store-User Relationship**
```php
// User belongs to one store (nullable)
User::store_id -> Store::id

// Store has many users
Store::id -> User::store_id (multiple users)
```

## 🔄 **User Flow**

### **1. Seller Registration**
```php
// When seller registers:
1. Create User
2. Create Store  
3. Assign store_id to user
4. User becomes store owner
```

### **2. Adding Users to Store**
```php
// Store owner adds users:
1. Create new user OR use existing user
2. Set user.store_id = store.id
3. User becomes part of the store
```

### **3. User Login & Access**
```php
// When user logs in:
1. Check if user.store_id exists
2. If yes -> User is a seller with store access
3. If no -> User is a regular buyer
```

## 📡 **API Endpoints**

### **Store Owner Endpoints**

#### **Get Store Users**
```http
GET /api/seller/stores/{storeId}/users
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "data": [
        {
            "id": 1,
            "name": "Store Owner",
            "email": "owner@store.com",
            "is_owner": true,
            "joined_at": "2025-01-07T10:00:00Z"
        },
        {
            "id": 2,
            "name": "Store Staff",
            "email": "staff@store.com", 
            "is_owner": false,
            "joined_at": "2025-01-07T11:00:00Z"
        }
    ]
}
```

#### **Add User to Store**
```http
POST /api/seller/stores/{storeId}/users/add
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "New Staff Member",
    "email": "staff@example.com",
    "password": "password123"
}
```

#### **Remove User from Store**
```http
DELETE /api/seller/stores/{storeId}/users/{userId}
Authorization: Bearer {token}
```

### **User Endpoints**

#### **Get User's Store**
```http
GET /api/store
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "data": {
        "store_id": 1,
        "store_name": "Tech Store",
        "store_email": "tech@store.com",
        "profile_image": "path/to/image.jpg",
        "is_owner": true
    }
}
```

## 🔐 **Authentication & Access**

### **Login Process**
```javascript
// User logs in normally
const loginResponse = await fetch('/api/login', {
    method: 'POST',
    body: JSON.stringify({
        email: 'user@example.com',
        password: 'password123'
    })
});

const { user, token } = await loginResponse.json();

// Check if user is a seller
if (user.store_id) {
    // User is a seller - show seller dashboard
    showSellerDashboard();
} else {
    // User is a buyer - show buyer interface
    showBuyerInterface();
}
```

### **Store Access Control**
```php
// In controllers, check if user has store access
public function getProducts()
{
    $user = Auth::user();
    
    if (!$user->store_id) {
        return ResponseHelper::error('User is not associated with any store', 403);
    }
    
    // Get products from user's store
    $products = Product::where('store_id', $user->store_id)->get();
    
    return ResponseHelper::success($products);
}
```

## 🎭 **User Types**

### **1. Store Owner**
- **Created during seller registration**
- **Full access to store management**
- **Can add/remove other users**
- **Cannot be removed from store**

### **2. Store User**
- **Added by store owner**
- **Full access to store operations**
- **Can be removed by store owner**
- **Same permissions as owner (simplified)**

## 📊 **Data Access Examples**

### **Products Management**
```php
// All users in store can manage products
public function getAllProducts()
{
    $user = Auth::user();
    
    if (!$user->store_id) {
        return ResponseHelper::error('Access denied', 403);
    }
    
    $products = Product::where('store_id', $user->store_id)->get();
    return ResponseHelper::success($products);
}
```

### **Orders Management**
```php
// All users in store can manage orders
public function getOrders()
{
    $user = Auth::user();
    
    if (!$user->store_id) {
        return ResponseHelper::error('Access denied', 403);
    }
    
    $orders = StoreOrder::where('store_id', $user->store_id)->get();
    return ResponseHelper::success($orders);
}
```

### **Analytics**
```php
// All users in store can view analytics
public function getAnalytics()
{
    $user = Auth::user();
    
    if (!$user->store_id) {
        return ResponseHelper::error('Access denied', 403);
    }
    
    $analytics = $this->calculateAnalytics($user->store_id);
    return ResponseHelper::success($analytics);
}
```

## 🚀 **Implementation Benefits**

### **1. Simplicity**
- ✅ **One field** - Just `store_id` in users table
- ✅ **No complex relationships** - Simple foreign key
- ✅ **Easy to understand** - One user = one store
- ✅ **Simple queries** - Direct store access

### **2. Performance**
- ✅ **Fast queries** - Direct foreign key lookup
- ✅ **No joins needed** - Simple where clauses
- ✅ **Efficient filtering** - Single field filtering

### **3. Security**
- ✅ **Store isolation** - Users only see their store data
- ✅ **Automatic filtering** - All data filtered by store_id
- ✅ **Simple access control** - Just check store_id exists

## 🔄 **Complete Flow Example**

### **1. Seller Registration**
```php
// Seller registers
$user = User::create([
    'full_name' => 'Store Owner',
    'email' => 'owner@store.com',
    'password' => Hash::make('password'),
    'role' => 'seller'
]);

$store = Store::create([
    'user_id' => $user->id,
    'store_name' => 'My Store',
    'store_email' => 'owner@store.com'
]);

// Assign store to user
$user->update(['store_id' => $store->id]);
```

### **2. Add Staff Member**
```php
// Store owner adds staff
$staff = User::create([
    'full_name' => 'Staff Member',
    'email' => 'staff@store.com',
    'password' => Hash::make('password'),
    'store_id' => $store->id  // Assign to store
]);
```

### **3. Staff Login & Access**
```php
// Staff logs in
$user = User::where('email', 'staff@store.com')->first();

if ($user->store_id) {
    // User is a seller - show seller interface
    $store = Store::find($user->store_id);
    // Show store dashboard
}
```

## 🎯 **Key Features**

### **✅ What Works**
- **Simple store assignment** - One field, one relationship
- **Automatic store access** - Users get full store access
- **Easy user management** - Add/remove users from store
- **Store isolation** - Users only see their store data
- **Simple authentication** - Check store_id exists

### **❌ What's Simplified**
- **No role-based permissions** - All users have same access
- **No complex relationships** - Just one foreign key
- **No invitation system** - Direct assignment
- **No permission checking** - Simple store access

## 📋 **Migration Steps**

### **1. Add store_id to users table**
```bash
php artisan make:migration add_store_id_to_users_table
```

### **2. Update User model**
```php
// Add store_id to fillable
protected $fillable = [..., 'store_id'];

// Add relationship
public function store()
{
    return $this->belongsTo(Store::class);
}
```

### **3. Update Store model**
```php
// Add relationship
public function users()
{
    return $this->hasMany(User::class);
}
```

### **4. Update seller registration**
```php
// After creating store, assign to user
$user->update(['store_id' => $store->id]);
```

### **5. Update all seller controllers**
```php
// Check if user has store access
if (!$user->store_id) {
    return ResponseHelper::error('Access denied', 403);
}

// Filter data by user's store
$data = Model::where('store_id', $user->store_id)->get();
```

## 🎉 **Final Result**

**Simple, effective store user management:**
- ✅ **One user = one store** (simple relationship)
- ✅ **Store owner can add users** (direct assignment)
- ✅ **All users get full store access** (no complex permissions)
- ✅ **Automatic data filtering** (by store_id)
- ✅ **Easy to implement and maintain** (minimal complexity)

This approach is much simpler and more practical for most use cases! 🏪👥✨
