<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getForUser(){
        try{
            $user=Auth::user();
            $notifications=UserNotification::where('user_id',$user->id)->latest()->get();
            return ResponseHelper::success($notifications);
        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage(),500);

        }
    }
    public function markAsRead($notificationId){
        try{
            $user=Auth::user();
            $notification=UserNotification::where('user_id',$user->id)->where('id',$notificationId)->first();
            if(!$notification){
                return ResponseHelper::error('Notification not found',404);
            }
            $notification->is_read=true;
            $notification->save();
            return ResponseHelper::success($notification);
        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage(),500);

        }
    }
}
