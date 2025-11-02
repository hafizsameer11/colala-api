<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserActivityController extends Controller
{
    /**
     * Heartbeat endpoint for frontend to ping periodically
     * Updates user's last_seen_at timestamp
     */
    public function heartbeat(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return ResponseHelper::error('Unauthenticated', 401);
            }

            // Update last_seen_at
            $user->touch('last_seen_at');

            return ResponseHelper::success([
                'is_online' => $user->isOnline(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'last_seen_formatted' => $user->getLastSeenFormatted(),
            ], 'Heartbeat received');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get current user's online status
     */
    public function getMyStatus(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return ResponseHelper::error('Unauthenticated', 401);
            }

            return ResponseHelper::success($user->getOnlineStatus());
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get online status for a specific user (if you need to check other users)
     */
    public function getUserStatus(Request $request, $userId)
    {
        try {
            $user = \App\Models\User::findOrFail($userId);

            return ResponseHelper::success($user->getOnlineStatus());
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
