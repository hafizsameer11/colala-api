<?php

namespace App\Helpers;
use App\Models\UserNotification;
use App\Models\User;
use App\Services\ExpoNotificationService;
use Illuminate\Support\Facades\Log;

class UserNotificationHelper{
    /**
     * Send both in-app notification and push notification
     * 
     * @param int $user_id
     * @param string $title
     * @param string $content
     * @param array $data Additional data for push notification
     * @return UserNotification
     */
    public static function notify($user_id, $title, $content, array $data = []){
        // Create in-app notification
        $notification = UserNotification::create([
            'user_id'=>$user_id,
            'title'=>$title,
            'content'=>$content,
            'is_read'=>false
        ]);

        // Send push notification if user has expo token
        try {
            $user = User::find($user_id);
            if ($user && !empty($user->expo_push_token)) {
                $expoService = new ExpoNotificationService();
                
                // Prepare notification data
                $pushData = array_merge([
                    'notification_id' => $notification->id,
                    'type' => 'general',
                    'title' => $title,
                    'body' => $content,
                ], $data);

                $expoService->sendNotification(
                    $user->expo_push_token,
                    $title,
                    $content,
                    $pushData
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the notification creation
            Log::error('Failed to send push notification: ' . $e->getMessage(), [
                'user_id' => $user_id,
                'title' => $title
            ]);
        }

        return $notification;
    }

    /**
     * Send push notification only (without in-app notification)
     * 
     * @param int $user_id
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|null
     */
    public static function pushOnly($user_id, $title, $body, array $data = []){
        try {
            $user = User::find($user_id);
            if ($user && !empty($user->expo_push_token)) {
                $expoService = new ExpoNotificationService();
                
                $pushData = array_merge([
                    'type' => 'general',
                ], $data);

                return $expoService->sendNotification(
                    $user->expo_push_token,
                    $title,
                    $body,
                    $pushData
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send push notification: ' . $e->getMessage(), [
                'user_id' => $user_id,
                'title' => $title
            ]);
        }
        return null;
    }
}