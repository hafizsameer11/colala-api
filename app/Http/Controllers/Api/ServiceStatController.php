<?php

// app/Http/Controllers/Api/ServiceStatController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ServiceStat;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceStatController extends Controller
{
    // Daily stats for chart
    public function getStats(Request $request, $serviceId)
    {
        $start = Carbon::now()->subDays(7);

        $stats = ServiceStat::where('service_id', $serviceId)
            ->where('created_at','>=',$start)
            ->selectRaw('DATE(created_at) as date, event_type, COUNT(*) as count')
            ->groupBy('date','event_type')
            ->orderBy('date')
            ->get();

        $result = [];
        foreach ($stats as $row) {
            $date = $row->date;
            if (!isset($result[$date])) {
                $result[$date] = [
                    'date'       => $date,
                    'impression' => 0,
                    'view'       => 0,
                    'click'      => 0,
                    'chat'       => 0,
                    'phone_view' => 0,
                ];
            }
            $result[$date][$row->event_type] = $row->count;
        }

        return ResponseHelper::success(array_values($result), "Service stats retrieved");
    }

    // Total counters
    public function totals($serviceId)
    {
        $totals = ServiceStat::where('service_id',$serviceId)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count','event_type');

        $default = [
            'impression'=>0,'view'=>0,'click'=>0,'chat'=>0,'phone_view'=>0,
        ];

        return ResponseHelper::success(array_merge($default,$totals->toArray()), "Total service stats");
    }
}
