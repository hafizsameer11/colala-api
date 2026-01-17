-- Update Apple Product IDs in subscription_plans table
-- Replace the product IDs below with your actual product IDs from App Store Connect

-- Basic Plan (id: 4)
UPDATE subscription_plans 
SET 
  apple_product_id_monthly = 'com.colalaseller.mall.basic',
  apple_product_id_annual = 'com.colalaseller.mall.basic.annual'  -- Update this when you create annual version
WHERE id = 4;

-- Pro Plan (id: 5)
-- Update these with your Pro plan product IDs from App Store Connect
UPDATE subscription_plans 
SET 
  apple_product_id_monthly = 'com.colalaseller.mall.pro',  -- Replace with actual product ID
  apple_product_id_annual = 'com.colalaseller.mall.pro.annual'  -- Replace with actual product ID
WHERE id = 5;

-- VIP Plan (id: 6)
-- Update these with your VIP plan product IDs from App Store Connect
UPDATE subscription_plans 
SET 
  apple_product_id_monthly = 'com.colalaseller.mall.vip',  -- Replace with actual product ID
  apple_product_id_annual = 'com.colalaseller.mall.vip.annual'  -- Replace with actual product ID
WHERE id = 6;

-- Gold Plan (id: 7)
-- Update these with your Gold plan product IDs from App Store Connect
UPDATE subscription_plans 
SET 
  apple_product_id_monthly = 'com.colalaseller.mall.gold',  -- Replace with actual product ID
  apple_product_id_annual = 'com.colalaseller.mall.gold.annual'  -- Replace with actual product ID
WHERE id = 7;

-- Verify the updates
SELECT 
  id, 
  name, 
  apple_product_id_monthly, 
  apple_product_id_annual 
FROM subscription_plans;

