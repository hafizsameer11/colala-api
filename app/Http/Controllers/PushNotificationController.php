<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushNotificationController extends Controller
{
    public function saveExpoPushToken(Request $request)
    {
        try{
            $user=Auth::user();
            $user->expo_push_token=$request->expo_push_token;
            $user->save();
            return ResponseHelper::success($user);
        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage(),500);
        }
    }
}
