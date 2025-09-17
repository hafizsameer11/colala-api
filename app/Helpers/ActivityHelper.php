<?php 

namespace App\Helpers;

use App\Models\UserActivity;
class ActivityHelper{

    public static function log($user_id, $activity){
      return  UserActivity::create([
            'user_id' => $user_id,
            'activity' => $activity
        ]);
    }
}
