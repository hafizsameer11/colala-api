<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\SystemPushNotification;
use App\Models\SystemNotificationRecipient;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ExpoNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminNotificationController extends Controller
{
    /**
     * Get all system push notifications
     */
    public function getAllNotifications(Request $request)
    {
        try {
            $query = SystemPushNotification::with(['creator', 'notificationRecipients'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by audience type
            if ($request->has('audience_type')) {
                $query->where('audience_type', $request->audience_type);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('message', 'like', "%{$search}%");
                });
            }

            $notifications = $query->paginate($request->get('per_page', 20));

            // Get statistics
            $stats = [
                'total_notifications' => SystemPushNotification::count(),
                'sent_notifications' => SystemPushNotification::where('status', 'sent')->count(),
                'scheduled_notifications' => SystemPushNotification::where('status', 'scheduled')->count(),
                'draft_notifications' => SystemPushNotification::where('status', 'draft')->count(),
                'total_recipients' => SystemNotificationRecipient::count(),
                'delivered_notifications' => SystemNotificationRecipient::where('delivery_status', 'delivered')->count(),
                'failed_notifications' => SystemNotificationRecipient::where('delivery_status', 'failed')->count(),
            ];

            return ResponseHelper::success([
                'notifications' => $this->formatNotificationsData($notifications),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create a new system push notification
     */
    public function createNotification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'message' => 'required|string|max:1000',
                'link' => 'nullable|url',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:10240', // 10MB max
                'audience_type' => 'required|in:all,buyers,sellers,specific',
                'target_user_ids' => 'required_if:audience_type,specific|array',
                'target_user_ids.*' => 'exists:users,id',
                'scheduled_for' => 'nullable|date|after:now',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error('Validation failed: ' . $validator->errors()->first(), 422);
            }

            DB::beginTransaction();

            // Handle attachment upload
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('notifications/attachments', 'public');
            }

            // Create notification
            $notification = SystemPushNotification::create([
                'title' => $request->title,
                'message' => $request->message,
                'link' => $request->link,
                'attachment' => $attachmentPath,
                'audience_type' => $request->audience_type,
                'target_user_ids' => $request->target_user_ids,
                'status' => $request->scheduled_for ? 'scheduled' : 'draft',
                'scheduled_for' => $request->scheduled_for,
                'created_by' => $request->user()->id,
            ]);

            // If not scheduled, send immediately
            if (!$request->scheduled_for) {
                $this->sendNotification($notification);
            }

            DB::commit();

            return ResponseHelper::success([
                'notification' => $this->formatNotificationData($notification),
                'message' => $request->scheduled_for ? 'Notification scheduled successfully' : 'Notification sent successfully'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send a notification immediately
     */
    public function sendNotification($notification)
    {
        try {
            // Get target users based on audience type
            $targetUsers = $this->getTargetUsers($notification);

            // Send Expo push notifications
            $expoService = new ExpoNotificationService();
            $sentCount = 0;
            $failedCount = 0;

            // Create recipient records and send notifications in a single loop
            foreach ($targetUsers as $user) {
                // Create recipient record
                $recipient = SystemNotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'delivery_status' => 'pending',
                    'device_token' => $user->expo_push_token ?? null,
                ]);

                // Create user notification record (in-app notification)
                UserNotification::create([
                    'user_id' => $user->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => 'system_push',
                    'data' => [
                        'link' => $notification->link,
                        'attachment' => $notification->attachment_url,
                        'notification_id' => $notification->id,
                    ],
                    'is_read' => false,
                ]);

                // Send Expo push notification if user has expo_push_token
                if (!empty($user->expo_push_token)) {
                    try {
                        $pushData = [
                            'notification_id' => $notification->id,
                            'type' => 'system_push',
                            'link' => $notification->link,
                            'attachment' => $notification->attachment_url,
                        ];

                        $expoService->sendNotification(
                            $user->expo_push_token,
                            $notification->title,
                            $notification->message,
                            $pushData
                        );

                        // Update recipient delivery status to delivered
                        $recipient->update(['delivery_status' => 'delivered']);
                        $sentCount++;
                    } catch (\Exception $e) {
                        // Update recipient delivery status to failed
                        $recipient->update([
                            'delivery_status' => 'failed',
                            'failure_reason' => $e->getMessage()
                        ]);

                        Log::error('Failed to send Expo push notification to user ' . $user->id . ': ' . $e->getMessage());
                        $failedCount++;
                    }
                } else {
                    // User doesn't have expo_push_token
                    $recipient->update([
                        'delivery_status' => 'pending',
                        'failure_reason' => 'No Expo push token available'
                    ]);
                }
            }

            // Update notification status
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info("Push notification sent", [
                'notification_id' => $notification->id,
                'total_recipients' => count($targetUsers),
                'sent_count' => $sentCount,
                'failed_count' => $failedCount
            ]);

            return true;

        } catch (Exception $e) {
            $notification->update(['status' => 'failed']);
            Log::error('Failed to send notification: ' . $e->getMessage(), [
                'notification_id' => $notification->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get target users based on audience type
     */
    private function getTargetUsers($notification)
    {
        switch ($notification->audience_type) {
            case 'all':
                return User::select('id', 'full_name', 'email', 'expo_push_token', 'role', 'is_active')
                    ->where('is_active', true)
                    ->get();
            
            case 'buyers':
                return User::select('id', 'full_name', 'email', 'expo_push_token', 'role', 'is_active')
                    ->where(function ($q) {
                        $q->where('role', 'buyer')
                          ->orWhereNull('role')
                          ->orWhere('role', '');
                    })
                    ->where('is_active', true)
                    ->whereDoesntHave('store') // Exclude sellers
                    ->get();
            
            case 'sellers':
                return User::select('id', 'full_name', 'email', 'expo_push_token', 'role', 'is_active')
                    ->where('role', 'seller')
                    ->where('is_active', true)
                    ->get();
            
            case 'specific':
                return User::select('id', 'full_name', 'email', 'expo_push_token', 'role', 'is_active')
                    ->whereIn('id', $notification->target_user_ids ?? [])
                          ->where('is_active', true)
                          ->get();
            
            default:
                return collect();
        }
    }

    /**
     * Get notification details
     */
    public function getNotificationDetails($id)
    {
        try {
            $notification = SystemPushNotification::with([
                'creator',
                'notificationRecipients.user'
            ])->findOrFail($id);

            return ResponseHelper::success([
                'notification' => $this->formatNotificationData($notification),
                'recipients' => $notification->notificationRecipients->map(function ($recipient) {
                    return [
                        'id' => $recipient->id,
                        'user' => [
                            'id' => $recipient->user->id,
                            'name' => $recipient->user->full_name,
                            'email' => $recipient->user->email,
                            'role' => $recipient->user->role,
                        ],
                        'delivery_status' => $recipient->delivery_status,
                        'delivered_at' => $recipient->delivered_at,
                        'failure_reason' => $recipient->failure_reason,
                    ];
                }),
                'statistics' => [
                    'total_recipients' => $notification->total_recipients,
                    'successful_deliveries' => $notification->successful_deliveries,
                    'failed_deliveries' => $notification->failed_deliveries,
                    'delivery_rate' => $notification->total_recipients > 0 
                        ? round(($notification->successful_deliveries / $notification->total_recipients) * 100, 2)
                        : 0,
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update notification status
     */
    public function updateNotificationStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:draft,scheduled,sent,failed',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error('Validation failed: ' . $validator->errors()->first(), 422);
            }

            $notification = SystemPushNotification::findOrFail($id);
            
            // If changing to sent, actually send the notification
            if ($request->status === 'sent' && $notification->status !== 'sent') {
                $this->sendNotification($notification);
            } else {
                $notification->update(['status' => $request->status]);
            }

            return ResponseHelper::success([
                'notification' => $this->formatNotificationData($notification),
                'message' => 'Notification status updated successfully'
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete notification
     */
    public function deleteNotification($id)
    {
        try {
            $notification = SystemPushNotification::findOrFail($id);
            
            // Delete attachment if exists
            if ($notification->attachment) {
                Storage::disk('public')->delete($notification->attachment);
            }
            
            $notification->delete();

            return ResponseHelper::success([], 'Notification deleted successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get users for audience selection
     */
    public function getUsersForAudience(Request $request)
    {
        try {
            $query = User::where('is_active', true);

            // Filter by role
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('user_code', 'like', "%{$search}%");
                });
            }

            $users = $query->select('id', 'full_name', 'email', 'user_code', 'role', 'profile_picture')
                          ->paginate($request->get('per_page', 50));

            return ResponseHelper::success([
                'users' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'email' => $user->email,
                        'user_code' => $user->user_code,
                        'role' => $user->role,
                        'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                    ];
                }),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get audience data with buyers and sellers arrays
     */
    public function getAudienceData(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $limit = $request->get('limit', 100);

            // Get buyers
            $buyersQuery = User::where('is_active', true)
                ->where('role', 'buyer')
                ->select('id', 'full_name', 'email', 'user_code', 'profile_picture');

            if ($search) {
                $buyersQuery->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('user_code', 'like', "%{$search}%");
                });
            }

            $buyers = $buyersQuery->limit($limit)->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'user_code' => $user->user_code,
                    'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                ];
            });

            // Get sellers with store information
            $sellersQuery = User::where('is_active', true)
                ->where('role', 'seller')
                ->with(['store:id,user_id,store_name,profile_image'])
                ->select('id', 'full_name', 'email', 'user_code');

            if ($search) {
                $sellersQuery->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('user_code', 'like', "%{$search}%")
                      ->orWhereHas('store', function ($storeQuery) use ($search) {
                          $storeQuery->where('store_name', 'like', "%{$search}%");
                      });
                });
            }

            $sellers = $sellersQuery->limit($limit)->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'user_code' => $user->user_code,
                    'store_name' => $user->store?->store_name,
                    'profile_picture' => $user->store?->profile_image ? asset('storage/' . $user->store->profile_image) : null,
                ];
            });

            // Get statistics
            $stats = [
                'total_buyers' => User::where('role', 'buyer')->where('is_active', true)->count(),
                'total_sellers' => User::where('role', 'seller')->where('is_active', true)->count(),
                'total_users' => User::where('is_active', true)->count(),
            ];

            return ResponseHelper::success([
                'buyers' => $buyers,
                'sellers' => $sellers,
                'statistics' => $stats,
                'search_term' => $search,
                'limit' => $limit,
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format notifications data
     */
    private function formatNotificationsData($notifications)
    {
        return $notifications->map(function ($notification) {
            return $this->formatNotificationData($notification);
        });
    }

    /**
     * Format single notification data
     */
    private function formatNotificationData($notification)
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'link' => $notification->link,
            'attachment' => $notification->attachment_url,
            'audience_type' => $notification->audience_type,
            'target_user_ids' => $notification->target_user_ids,
            'status' => $notification->status,
            'scheduled_for' => $notification->scheduled_for,
            'sent_at' => $notification->sent_at,
            'created_by' => [
                'id' => $notification->creator->id,
                'name' => $notification->creator->full_name,
                'email' => $notification->creator->email,
            ],
            'total_recipients' => $notification->total_recipients,
            'successful_deliveries' => $notification->successful_deliveries,
            'failed_deliveries' => $notification->failed_deliveries,
            'created_at' => $notification->created_at,
            'updated_at' => $notification->updated_at,
        ];
    }
}
