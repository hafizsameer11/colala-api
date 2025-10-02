<?php 

// app/Services/BoostProductService.php
namespace App\Services;

use App\Models\BoostProduct;

class BoostProductService
{
    // tweakable knobs
    const MIN_DAILY_BUDGET = 100;          // minor units
    const PLATFORM_FEE_PCT = 0.07;         // 7%
    const REACH_PER_1000   = 4500;         // ~4.5k impressions per ₦1000
    const CLICK_THROUGH    = 0.012;        // 1.2%
    const CPC_FLOOR        = 5.00;

    public function computeTotals(int $dailyBudget, int $durationDays): array
    {
        $raw  = $dailyBudget * $durationDays;
        $fee  = (int) round($raw * self::PLATFORM_FEE_PCT);
        $total = $raw + $fee;

        $impressions = (int) round(($raw / 1000) * self::REACH_PER_1000);
        $clicks = (int) max(1, round($impressions * self::CLICK_THROUGH));
        $cpc = $clicks ? round($raw / $clicks, 2) : self::CPC_FLOOR;

        return [
            'platform_fee' => $fee,
            'subtotal'     => $raw,
            'total'        => $total,
            'reach'        => $impressions,
            'est_clicks'   => $clicks,
            'est_cpc'      => $cpc,
        ];
    }

    public function create(array $validated, int $storeId): BoostProduct
    {
        $calc = $this->computeTotals((int)$validated['budget'], (int)$validated['duration']);

        return BoostProduct::create([
            'product_id'     => $validated['product_id'],
            'store_id'       => $storeId,
            'location'       => $validated['location'] ?? null,
            'duration'       => $validated['duration'],
            'budget'         => $validated['budget'],
            'total_amount'   => $calc['total'],
            'reach'          => $calc['reach'],
            'cpc'            => $calc['est_cpc'],
            'start_date'     => $validated['start_date'] ?? null,
            'status'         => isset($validated['start_date']) ? 'scheduled' : 'running',
            'payment_method' => $validated['payment_method'] ?? 'wallet',
            'payment_status' => 'pending',
        ]);
    }
}
