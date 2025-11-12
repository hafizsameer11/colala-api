<?php 

// app/Http/Controllers/Api/ProductStatController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ProductStat;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProductStatController extends Controller
{
    public function getStats(Request $request, $productId)
    {
        $start = Carbon::now()->subDays(7); // last 7 days for chart
        //if product Stat does not exist create it
        $stats = ProductStat::where('product_id', $productId)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, event_type, COUNT(*) as count')
            ->groupBy('date','event_type')
            ->orderBy('date')
            ->get();

        // Restructure for chart
        $result = [];
        foreach ($stats as $row) {
            $date = $row->date;
            if (!isset($result[$date])) {
                $result[$date] = [
                    'date'       => $date,
                    'impression' => 0,
                    'view'       => 0,
                    'click'      => 0,
                    'add_to_cart'=> 0,
                    'order'      => 0,
                    'chat'       => 0,
                ];
            }
            $result[$date][$row->event_type] = $row->count;
        }

        return ResponseHelper::success(array_values($result), "Stats retrieved");
    }

    public function totals(Request $request, $productId)
    {
        $default = [
            'impression' => 0,
            'view' => 0,
            'click' => 0,
            'add_to_cart' => 0,
            'order' => 0,
            'chat' => 0,
        ];

        // Define all filter periods
        $filters = [
            'today' => [
                'label' => 'Today',
                'start_date' => Carbon::today()->startOfDay(),
            ],
            '7_days' => [
                'label' => 'Last 7 Days',
                'start_date' => Carbon::now()->subDays(7)->startOfDay(),
            ],
            '14_days' => [
                'label' => 'Last 14 Days',
                'start_date' => Carbon::now()->subDays(14)->startOfDay(),
            ],
            '30_days' => [
                'label' => 'Last 30 Days',
                'start_date' => Carbon::now()->subDays(30)->startOfDay(),
            ],
            '90_days' => [
                'label' => 'Last 90 Days',
                'start_date' => Carbon::now()->subDays(90)->startOfDay(),
            ],
            'all_time' => [
                'label' => 'All Time',
                'start_date' => null,
            ],
        ];

        $result = [];
        $endDate = Carbon::now();

        // Get stats for each filter period
        foreach ($filters as $filterKey => $filterConfig) {
            $query = ProductStat::where('product_id', $productId);
            
            // Apply date filter if specified
            if ($filterConfig['start_date']) {
                $query->where('created_at', '>=', $filterConfig['start_date']);
            }
            
            $totals = $query
                ->selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type');

            $stats = array_merge($default, $totals->toArray());
            
            $result[$filterKey] = [
                'label' => $filterConfig['label'],
                'stats' => $stats,
                'period' => [
                    'start_date' => $filterConfig['start_date'] ? $filterConfig['start_date']->format('Y-m-d H:i:s') : null,
                    'end_date' => $endDate->format('Y-m-d H:i:s'),
                ],
            ];
        }

        return ResponseHelper::success($result, "Total stats");
    }
}
