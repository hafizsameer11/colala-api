<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
     * Apply date filter (period or custom date range) to a query builder
     * Priority: period > date_from/date_to > date_range (legacy)
     * 
     * @param Builder $query
     * @param Request $request
     * @param string $dateColumn Default is 'created_at'
     * @return Builder
     */
    protected function applyDateFilter(Builder $query, $request, $dateColumn = 'created_at')
    {
        // Priority 1: Period parameter
        $period = $request->get('period');
        if ($period && $period !== 'all_time' && $period !== 'null') {
            if ($this->isValidPeriod($period)) {
                $dateRange = $this->getDateRange($period);
                if ($dateRange) {
                    $query->whereBetween($dateColumn, [$dateRange['start'], $dateRange['end']]);
                }
                return $query;
            }
        }

        // Priority 2: Custom date range (date_from and date_to)
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            
            // Validate date format
            try {
                $startDate = \Carbon\Carbon::parse($dateFrom)->startOfDay();
                $endDate = \Carbon\Carbon::parse($dateTo)->endOfDay();
                
                if ($startDate->lte($endDate)) {
                    $query->whereBetween($dateColumn, [$startDate, $endDate]);
                }
            } catch (\Exception $e) {
                // Invalid date format, skip custom date filter
            }
            return $query;
        }

        // Priority 3: Legacy date_range parameter (for backward compatibility)
        if ($request->has('date_range') && $request->date_range !== 'all') {
            switch ($request->date_range) {
                case 'today':
                    $query->whereDate($dateColumn, today());
                    break;
                case 'this_week':
                    $query->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()]);
                    break;
            }
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
