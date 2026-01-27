-- ============================================
-- SQL Queries to Check Completed Orders for Buyers
-- ============================================

-- 1. Total completed orders for buyers (including both 'completed' and 'delivered' status)
SELECT COUNT(*) as total_completed_orders
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE so.status IN ('completed', 'delivered')
  AND (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL;  -- Exclude users who have stores (sellers)

-- 2. Breakdown by status (completed vs delivered)
SELECT 
    so.status,
    COUNT(*) as count
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE so.status IN ('completed', 'delivered')
  AND (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL
GROUP BY so.status;

-- 3. Detailed list of completed orders for buyers
SELECT 
    so.id as store_order_id,
    so.status,
    o.order_no,
    o.id as order_id,
    u.id as user_id,
    u.full_name as buyer_name,
    u.email as buyer_email,
    u.role,
    so.created_at as order_date,
    so.subtotal_with_shipping as total_amount
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE so.status IN ('completed', 'delivered')
  AND (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL
ORDER BY so.created_at DESC;

-- 4. Completed orders count by date (last 30 days)
SELECT 
    DATE(so.created_at) as order_date,
    COUNT(*) as completed_orders_count
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE so.status IN ('completed', 'delivered')
  AND (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL
  AND so.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(so.created_at)
ORDER BY order_date DESC;

-- 5. Summary statistics for buyer orders
SELECT 
    COUNT(*) as total_store_orders,
    SUM(CASE WHEN so.status IN ('completed', 'delivered') THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN so.status = 'pending' OR so.status = 'pending_acceptance' OR so.status = 'order_placed' OR so.status = 'processing' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN so.status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery,
    SUM(CASE WHEN so.status = 'delivered' THEN 1 ELSE 0 END) as delivered_only,
    SUM(CASE WHEN so.status = 'completed' THEN 1 ELSE 0 END) as completed_only,
    SUM(CASE WHEN so.status = 'disputed' THEN 1 ELSE 0 END) as disputed
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL;

-- 6. Check if there are any orders with status 'delivered' that should be counted
SELECT 
    COUNT(*) as delivered_orders_count,
    'These are included in completed_orders count' as note
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE so.status = 'delivered'
  AND (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL;

-- 7. Check if there are any orders with status 'completed' that should be counted
SELECT 
    COUNT(*) as completed_orders_count,
    'These are included in completed_orders count' as note
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE so.status = 'completed'
  AND (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL;

-- 8. Verify the relationship: Check all statuses for buyer orders
SELECT 
    so.status,
    COUNT(*) as count,
    GROUP_CONCAT(DISTINCT so.id ORDER BY so.id LIMIT 5) as sample_store_order_ids
FROM store_orders so
INNER JOIN orders o ON so.order_id = o.id
INNER JOIN users u ON o.user_id = u.id
LEFT JOIN stores s ON u.id = s.user_id
WHERE (u.role = 'buyer' OR u.role IS NULL OR u.role = '')
  AND s.id IS NULL
GROUP BY so.status
ORDER BY count DESC;

