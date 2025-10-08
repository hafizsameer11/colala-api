# Seller Access Control for Invited Users

## 🔐 **How Invited Users Access Seller Data**

### **1. Login Process**
```javascript
// Invited user logs in normally
POST /api/login
{
    "email": "invited@example.com",
    "password": "password123"
}

// Response includes store access information
{
    "status": true,
    "data": {
        "user": {
            "id": 2,
            "name": "Invited User",
            "email": "invited@example.com"
        },
        "token": "1|abc123...",
        "stores": [
            {
                "store_id": 1,
                "store_name": "Tech Store",
                "role": "manager",
                "permissions": ["manage_products", "manage_orders"]
            }
        ]
    }
}
```

### **2. Data Binding Examples**

#### **Product Management**
```php
// ProductController with role-based access
class ProductController extends Controller
{
    public function getAll()
    {
        // Get user's accessible stores
        $user = Auth::user();
        $accessibleStores = $user->stores()->pluck('store_id');
        
        // Only show products from accessible stores
        $products = Product::whereIn('store_id', $accessibleStores)
            ->with(['images', 'variants'])
            ->get();
            
        return ResponseHelper::success($products);
    }
    
    public function create(Request $request)
    {
        // Check if user has permission to manage products
        $storeId = $request->store_id;
        
        if (!$user->hasStorePermission($storeId, 'manage_products')) {
            return ResponseHelper::error('You do not have permission to manage products', 403);
        }
        
        // Create product logic...
    }
}
```

#### **Order Management**
```php
// OrderController with store filtering
class SellerOrderController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $accessibleStores = $user->stores()->pluck('store_id');
        
        // Only show orders from accessible stores
        $orders = StoreOrder::whereIn('store_id', $accessibleStores)
            ->with(['order', 'store'])
            ->get();
            
        return ResponseHelper::success($orders);
    }
}
```

### **3. Role-Based Data Access**

#### **Admin User (Full Access)**
```json
{
    "user_role": "admin",
    "accessible_stores": [1, 2, 3],
    "permissions": [
        "manage_products",
        "manage_orders", 
        "manage_customers",
        "manage_analytics",
        "manage_settings",
        "manage_users",
        "manage_inventory",
        "manage_promotions"
    ],
    "data_access": {
        "products": "All products from assigned stores",
        "orders": "All orders from assigned stores", 
        "customers": "All customers from assigned stores",
        "analytics": "Full analytics access",
        "settings": "Store settings access",
        "users": "Can invite/remove users"
    }
}
```

#### **Manager User (Limited Access)**
```json
{
    "user_role": "manager",
    "accessible_stores": [1, 2],
    "permissions": [
        "manage_products",
        "manage_orders",
        "manage_customers", 
        "manage_analytics",
        "manage_inventory",
        "manage_promotions"
    ],
    "data_access": {
        "products": "All products from assigned stores",
        "orders": "All orders from assigned stores",
        "customers": "All customers from assigned stores", 
        "analytics": "Full analytics access",
        "settings": "No access",
        "users": "No access"
    }
}
```

#### **Staff User (Basic Access)**
```json
{
    "user_role": "staff",
    "accessible_stores": [1],
    "permissions": [
        "manage_orders",
        "manage_customers",
        "view_analytics"
    ],
    "data_access": {
        "products": "No access",
        "orders": "All orders from assigned stores",
        "customers": "All customers from assigned stores",
        "analytics": "View-only access",
        "settings": "No access", 
        "users": "No access"
    }
}
```

## 🛡️ **Middleware Protection**

### **Route Protection Examples**
```php
// Protect product management routes
Route::middleware(['auth:sanctum', 'store.access:manage_products'])->group(function () {
    Route::post('products', [ProductController::class, 'create']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'delete']);
});

// Protect order management routes  
Route::middleware(['auth:sanctum', 'store.access:manage_orders'])->group(function () {
    Route::get('orders', [SellerOrderController::class, 'index']);
    Route::put('orders/{id}/status', [SellerOrderController::class, 'updateStatus']);
});

// Protect analytics routes
Route::middleware(['auth:sanctum', 'store.access:manage_analytics'])->group(function () {
    Route::get('analytics', [SellerAnalyticsController::class, 'index']);
});
```

## 📊 **Data Filtering Implementation**

### **Service Layer Updates**
```php
// ProductService with store filtering
class ProductService
{
    public function getAll()
    {
        $user = Auth::user();
        $storeId = Store::where('user_id', $user->id)->pluck('id')->first();
        
        // If user is store owner, get all products
        if ($storeId) {
            return Product::where('store_id', $storeId)->get();
        }
        
        // If user is invited, get products from accessible stores
        $accessibleStores = $user->stores()->pluck('store_id');
        return Product::whereIn('store_id', $accessibleStores)->get();
    }
}
```

### **Order Service with Role Filtering**
```php
// SellerOrderService with access control
class SellerOrderService  
{
    public function getOrders()
    {
        $user = Auth::user();
        
        // Get accessible stores based on user role
        if ($user->stores()->exists()) {
            // Invited user - filter by accessible stores
            $accessibleStores = $user->stores()->pluck('store_id');
            return StoreOrder::whereIn('store_id', $accessibleStores)->get();
        } else {
            // Store owner - get all store orders
            $storeId = Store::where('user_id', $user->id)->pluck('id')->first();
            return StoreOrder::where('store_id', $storeId)->get();
        }
    }
}
```

## 🔄 **Complete Data Flow**

### **1. User Login**
```javascript
// User logs in
const loginResponse = await fetch('/api/login', {
    method: 'POST',
    body: JSON.stringify({
        email: 'invited@example.com',
        password: 'password123'
    })
});

const { user, token, stores } = await loginResponse.json();
```

### **2. Store Selection**
```javascript
// User selects which store to work with
const selectedStore = stores[0]; // User can switch between stores
localStorage.setItem('selectedStore', JSON.stringify(selectedStore));
```

### **3. API Calls with Store Context**
```javascript
// All API calls include store context
const response = await fetch('/api/seller/products', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'X-Store-ID': selectedStore.store_id
    }
});
```

### **4. Backend Processing**
```php
// Middleware extracts store context
class StoreAccessMiddleware
{
    public function handle($request, $next, $permission = null)
    {
        $user = $request->user();
        $storeId = $request->header('X-Store-ID') ?? $request->route('storeId');
        
        // Verify user has access to this store
        if (!$user->hasStoreAccess($storeId)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        // Check specific permission
        if ($permission && !$user->hasStorePermission($storeId, $permission)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        
        // Add store context to request
        $request->merge(['current_store_id' => $storeId]);
        
        return $next($request);
    }
}
```

## 🎯 **Key Benefits**

### **1. Seamless Integration**
- ✅ **Same login process** - No separate seller login
- ✅ **Automatic store access** - Based on invitations
- ✅ **Role-based restrictions** - Automatic permission enforcement

### **2. Data Security**
- ✅ **Store isolation** - Users only see their assigned stores
- ✅ **Permission filtering** - Data filtered by user permissions
- ✅ **Role-based access** - Different data access per role

### **3. Multi-Store Support**
- ✅ **Multiple stores** - Users can access multiple stores
- ✅ **Store switching** - Easy store context switching
- ✅ **Unified interface** - Same seller interface for all users

## 🚀 **Implementation Summary**

**Yes, invited users can login as sellers and access all seller data, but with these restrictions:**

1. **Login**: Same login process as regular users
2. **Store Access**: Only stores they're invited to
3. **Data Filtering**: Only data from accessible stores
4. **Permission Control**: Role-based access to features
5. **Multi-Store**: Can work with multiple stores

The system automatically filters all data based on the user's store relationships and role permissions! 🏪👥✨
