<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait PeriodFilterTrait
{
    /**
     * Get date range based on period parameter
     * 
     * @param string|null $period
     * @return array|null
     */
    protected function getDateRange($period)
    {
        if (!$period || $period === 'all_time' || $period === 'null') {
            return null;
        }

        switch ($period) {
            case 'today':
                return [
                    'start' => now()->startOfDay(),
                    'end' => now()->endOfDay(),
                    'previous_start' => now()->subDay()->startOfDay(),
                    'previous_end' => now()->subDay()->endOfDay()
                ];
            case 'this_week':
                return [
                    'start' => now()->startOfWeek(),
                    'end' => now()->endOfWeek(),
                    'previous_start' => now()->subWeek()->startOfWeek(),
                    'previous_end' => now()->subWeek()->endOfWeek()
                ];
            case 'this_month':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                    'previous_start' => now()->subMonth()->startOfMonth(),
                    'previous_end' => now()->subMonth()->endOfMonth()
                ];
            case 'last_month':
                return [
                    'start' => now()->subMonth()->startOfMonth(),
                    'end' => now()->subMonth()->endOfMonth(),
                    'previous_start' => now()->subMonths(2)->startOfMonth(),
                    'previous_end' => now()->subMonths(2)->endOfMonth()
                ];
            case 'this_year':
                return [
                    'start' => now()->startOfYear(),
                    'end' => now()->endOfYear(),
                    'previous_start' => now()->subYear()->startOfYear(),
                    'previous_end' => now()->subYear()->endOfYear()
                ];
            default:
                return null; // All time
        }
    }

    /**
     * Apply period filter to a query builder
     * 
     * @param Builder $query
     * @param string|null $period
     * @param string $dateColumn Default is 'created_at'
     * @return Builder
     */
    protected function applyPeriodFilter(Builder $query, $period, $dateColumn = 'created_at')
    {
        $dateRange = $this->getDateRange($period);
        
        if ($dateRange) {
            $query->whereBetween($dateColumn, [$dateRange['start'], $dateRange['end']]);
        }
        
        return $query;
    }

    /**
     * Validate period parameter
     * 
     * @param string|null $period
     * @return bool
     */
    protected function isValidPeriod($period)
    {
        if (!$period || $period === 'all_time' || $period === 'null') {
            return true;
        }

        $validPeriods = ['today', 'this_week', 'this_month', 'last_month', 'this_year'];
        return in_array($period, $validPeriods);
    }

    /**
     * Calculate percentage increase
     * 
     * @param float|int $current
     * @param float|int $previous
     * @return float
     */
    protected function calculateIncrease($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
