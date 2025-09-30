<?php

namespace App\Helpers;
use App\Models\UserNotification;
class UserNotificationHelper{
    public static function notify($user_id, $title, $content){
        return UserNotification::create([
            'user_id'=>$user_id,
            'title'=>$title,
            'content'=>$content,
            'is_read'=>false
        ]);
    }
}