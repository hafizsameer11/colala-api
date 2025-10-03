<?php 

// app/Helpers/ServiceStatHelper.php
namespace App\Helpers;

use App\Models\ServiceStat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ServiceStatHelper
{
    /**
     * Record an event for a service
     *
     * @param int $serviceId
     * @param string $eventType (view, impression, click, chat, phone_view)
     * @return ServiceStat
     */
    public static function record(int $serviceId, string $eventType): ServiceStat
    {
        return ServiceStat::create([
            'service_id' => $serviceId,
            'event_type' => $eventType,
            'user_id'    => Auth::id(),
            'ip'         => Request::ip(),
        ]);
    }
}
