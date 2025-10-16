<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{
    /**
     * Get user notifications
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);
            $status = $request->get('status'); // 'read', 'unread', or null for all

            $query = UserNotification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($status === 'read') {
                $query->where('is_read', true);
            } elseif ($status === 'unread') {
                $query->where('is_read', false);
            }

            $notifications = $query->paginate($perPage);

            return ResponseHelper::success([
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
                'unread_count' => UserNotification::where('user_id', $user->id)->where('is_read', false)->count()
            ]);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        try {
            $user = Auth::user();
            $notification = UserNotification::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $notification->update(['is_read' => true]);

            return ResponseHelper::success($notification, 'Notification marked as read');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            UserNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return ResponseHelper::success(null, 'All notifications marked as read');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete notification
     */
    public function delete($id)
    {
        try {
            $user = Auth::user();
            $notification = UserNotification::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $notification->delete();

            return ResponseHelper::success(null, 'Notification deleted');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function stats()
    {
        try {
            $user = Auth::user();
            
            $stats = [
                'total_notifications' => UserNotification::where('user_id', $user->id)->count(),
                'unread_notifications' => UserNotification::where('user_id', $user->id)->where('is_read', false)->count(),
                'read_notifications' => UserNotification::where('user_id', $user->id)->where('is_read', true)->count(),
            ];

            return ResponseHelper::success($stats);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

