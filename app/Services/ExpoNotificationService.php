<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    /**
     * Send notification via Expo Push API (for iOS devices).
     *
     * @param string $expoToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendNotification(string $expoToken, string $title, string $body, array $data = []): array
    {
        // Expo Push API expects an array of notification objects
        // Also, 'data' must be an object (associative array), not an indexed array
        $notification = [
            'to' => $expoToken,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
        ];

        // Only include 'data' if it's not empty
        // Laravel will convert associative array to JSON object automatically
        if (!empty($data)) {
            $notification['data'] = $data;
        }

        // Wrap in array as Expo API expects array of notifications
        $payload = [$notification];

        try {
            $response = Http::post('https://exp.host/--/api/v2/push/send', $payload);
            $jsonResponse = $response->json();

            Log::info("📨 Expo push notification response", $jsonResponse);

            return $jsonResponse;
        } catch (\Exception $e) {
            Log::error("❌ Failed to send Expo notification: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
