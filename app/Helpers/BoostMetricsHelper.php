<?php

namespace App\Helpers;

use App\Models\BoostProduct;
use Illuminate\Support\Facades\Log;

class BoostMetricsHelper
{
    /**
     * Get the latest active boost for a product
     */
    public static function getActiveBoost(int $productId): ?BoostProduct
    {
        return BoostProduct::where('product_id', $productId)
            ->whereIn('status', ['running', 'scheduled'])
            ->where('payment_status', 'paid')
            ->latest('start_date')
            ->latest('created_at')
            ->first();
    }

    /**
     * Record an impression for a boosted product
     * Updates impressions, reach, and calculates amount spent
     */
    public static function recordImpression(int $productId): void
    {
        try {
            $boost = self::getActiveBoost($productId);
            
            if (!$boost) {
                return;
            }

            // Increment impressions and reach
            $boost->increment('impressions');
            $boost->increment('reach');

            // Calculate amount spent based on impressions
            // Cost per impression = (daily_budget / estimated_daily_impressions)
            // Estimated daily impressions = (budget / 1000) * REACH_PER_1000 = 4500
            $estimatedDailyImpressions = max(1, (int)round(($boost->budget / 1000) * 4500));
            $costPerImpression = $boost->budget / max(1, $estimatedDailyImpressions);
            
            // Increment amount spent (in minor units, so round to integer)
            $boost->increment('amount_spent', max(1, (int)round($costPerImpression)));
            
            // Recalculate CPC if there are clicks
            $boost->refresh();
            if ($boost->clicks > 0) {
                $boost->cpc = round($boost->amount_spent / $boost->clicks, 2);
            }
            $boost->save();
        } catch (\Exception $e) {
            Log::error('Failed to record boost impression: ' . $e->getMessage(), [
                'product_id' => $productId,
                'exception' => get_class($e),
            ]);
        }
    }

    /**
     * Record a click/view for a boosted product
     * Updates clicks, calculates CPC, and amount spent
     */
    public static function recordClick(int $productId): void
    {
        try {
            $boost = self::getActiveBoost($productId);
            
            if (!$boost) {
                return;
            }

            // Increment clicks
            $boost->increment('clicks');

            // Calculate cost per click based on budget and estimated clicks
            // Estimated clicks = estimated impressions * CLICK_THROUGH (1.2%)
            // Estimated impressions = (budget / 1000) * 4500
            $estimatedImpressions = max(1, (int)round(($boost->budget / 1000) * 4500));
            $estimatedClicks = max(1, (int)round($estimatedImpressions * 0.012));
            $costPerClick = $boost->budget / max(1, $estimatedClicks);
            
            // Increment amount spent by cost per click (in minor units)
            $boost->increment('amount_spent', max(1, (int)round($costPerClick)));
            
            // Recalculate CPC: amount_spent / clicks
            $boost->refresh();
            if ($boost->clicks > 0) {
                $boost->cpc = round($boost->amount_spent / $boost->clicks, 2);
            } else {
                $boost->cpc = 0;
            }
            
            $boost->save();
        } catch (\Exception $e) {
            Log::error('Failed to record boost click: ' . $e->getMessage(), [
                'product_id' => $productId,
                'exception' => get_class($e),
            ]);
        }
    }

    /**
     * Record add to cart for a boosted product
     * This is also considered a click
     */
    public static function recordAddToCart(int $productId): void
    {
        // Add to cart is also a click, so use the same logic
        self::recordClick($productId);
    }
}

