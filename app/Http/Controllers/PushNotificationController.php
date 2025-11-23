<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Services\ExpoNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushNotificationController extends Controller
{
    protected $expoNotificationService;
    public function __construct(ExpoNotificationService $expoNotificationService)
    {
        $this->expoNotificationService=$expoNotificationService;
    }
    public function saveExpoPushToken(Request $request)
    {
        try{
            $user=Auth::user();
            $user=User::find($user->id);
            $user->expo_push_token=$request->expoPushToken;
            $user->save();
            return ResponseHelper::success($user);
        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage(),500);
        }
    }
    public function testExpoNotification($userId)
    {
        try{
            $user=User::find($userId);
            // $user->expo_push_token=$request->expoPushToken;
            // $user->save();
           $notification= $this->expoNotificationService->sendNotification($user->expo_push_token, 'Test Notification', 'This is a test notification');
            return ResponseHelper::success($notification);
        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage(),500);
        }
    }
}
