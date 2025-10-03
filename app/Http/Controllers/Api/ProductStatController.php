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

    public function totals($productId)
    {
        $totals = ProductStat::where('product_id',$productId)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count','event_type');

        $default = [
            'impression'=>0,'view'=>0,'click'=>0,'add_to_cart'=>0,'order'=>0,'chat'=>0,
        ];

        return ResponseHelper::success(array_merge($default,$totals->toArray()), "Total stats");
    }
}
