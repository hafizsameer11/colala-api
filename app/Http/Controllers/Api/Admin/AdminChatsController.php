<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminChatsController extends Controller
{
    /**
     * Get all chats with filtering and pagination
     */
    public function getAllChats(Request $request)
    {
        try {
            $query = Chat::with(['store.user', 'user', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }]);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                switch ($request->status) {
                    case 'unread':
                        $query->whereHas('messages', function ($q) {
                            $q->where('is_read', false);
                        });
                        break;
                    case 'read':
                        $query->whereDoesntHave('messages', function ($q) {
                            $q->where('is_read', false);
                        });
                        break;
                    case 'dispute':
                        $query->where('type', 'dispute');
                        break;
                    case 'support':
                        $query->where('type', 'support');
                        break;
                    case 'general':
                        $query->where('type', 'general');
                        break;
                }
            }

            if ($request->has('date_range')) {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                        break;
                }
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery->where('store_name', 'like', "%{$search}%");
                    })->orWhereHas('messages', function ($messageQuery) use ($search) {
                        $messageQuery->where('message', 'like', "%{$search}%");
                    });
                });
            }

            $chats = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics
            $stats = [
                'total_chats' => Chat::count(),
                'unread_chats' => Chat::whereHas('messages', function ($q) {
                    $q->where('is_read', false);
                })->count(),
                'read_chats' => Chat::whereDoesntHave('messages', function ($q) {
                    $q->where('is_read', false);
                })->count(),
                'dispute_chats' => Chat::where('type', 'dispute')->count(),
                'support_chats' => Chat::where('type', 'support')->count(),
                'general_chats' => Chat::where('type', 'general')->count(),
            ];

            return ResponseHelper::success([
                'chats' => $this->formatChatsData($chats),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $chats->currentPage(),
                    'last_page' => $chats->lastPage(),
                    'per_page' => $chats->perPage(),
                    'total' => $chats->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed chat information
     */
    public function getChatDetails($chatId)
    {
        try {
            $chat = Chat::with([
                'store.user',
                'user',
                'messages.user',
                'storeOrder.order'
            ])->findOrFail($chatId);

            $chatData = [
                'chat_info' => [
                    'id' => $chat->id,
                    'type' => $chat->type,
                    'is_read' => $chat->is_read,
                    'status' => $chat->status,
                    'created_at' => $chat->created_at,
                    'updated_at' => $chat->updated_at,
                ],
                'store_info' => [
                    'store_id' => $chat->store->id,
                    'store_name' => $chat->store->store_name,
                    'store_email' => $chat->store->store_email,
                    'store_phone' => $chat->store->store_phone,
                    'store_location' => $chat->store->store_location,
                    'store_profile_image' => $chat->store->profile_image ? asset('storage/' . $chat->store->profile_image) : null,
                    'store_banner_image' => $chat->store->banner_image ? asset('storage/' . $chat->store->banner_image) : null,
                    'seller_name' => $chat->store->user->full_name,
                    'seller_email' => $chat->store->user->email,
                    'seller_phone' => $chat->store->user->phone,
                    'seller_profile_image' => $chat->store->user->profile_picture ? asset('storage/' . $chat->store->user->profile_picture) : null,
                ],
                'customer_info' => [
                    'customer_id' => $chat->user->id,
                    'customer_name' => $chat->user->full_name,
                    'customer_email' => $chat->user->email,
                    'customer_phone' => $chat->user->phone,
                    'customer_profile_image' => $chat->user->profile_picture ? asset('storage/' . $chat->user->profile_picture) : null,
                ],
                'order_info' => $chat->storeOrder ? [
                    'store_order_id' => $chat->storeOrder->id,
                    'order_id' => $chat->storeOrder->order_id,
                    'order_no' => $chat->storeOrder->order->order_no,
                    'grand_total' => $chat->storeOrder->order->grand_total,
                    'payment_status' => $chat->storeOrder->order->payment_status,
                ] : null,
                'messages' => $chat->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_type' => $message->sender_type,
                        'user_name' => $message->user ? $message->user->full_name : 'System',
                        'user_profile_image' => $message->user ? ($message->user->profile_picture ? asset('storage/' . $message->user->profile_picture) : null) : null,
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at,
                        'formatted_date' => $message->created_at->format('d-m-Y H:i A'),
                    ];
                }),
                'chat_statistics' => [
                    'total_messages' => $chat->messages->count(),
                    'unread_messages' => $chat->messages->where('is_read', false)->count(),
                    'user_messages' => $chat->messages->where('sender_type', 'buyer')->count(), // ChatMessage enum only allows 'buyer' or 'store'
                    'admin_messages' => $chat->messages->where('sender_type', 'store')->count(), // Using 'store' for admin messages
                ],
            ];

            return ResponseHelper::success($chatData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send message to chat
     */
    public function sendMessage(Request $request, $chatId)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
                'sender_type' => 'required|in:buyer,store',
            ]);

            $chat = Chat::findOrFail($chatId);

            $message = ChatMessage::create([
                'chat_id' => $chat->id,
                'sender_id' => $request->user()->id,
                'message' => $request->message,
                'sender_type' => $request->sender_type,
                'is_read' => false,
            ]);

            // Mark chat as unread if message is from user
            if ($request->sender_type === 'buyer') { // ChatMessage enum only allows 'buyer' or 'store'
                $chat->update(['is_read' => false]);
            }

            return ResponseHelper::success([
                'message_id' => $message->id,
                'chat_id' => $chat->id,
                'message' => $message->message,
                'sender_type' => $message->sender_type,
                'created_at' => $message->created_at,
            ], 'Message sent successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark chat as read
     */
    public function markChatAsRead($chatId)
    {
        try {
            $chat = Chat::findOrFail($chatId);
            
            $chat->update(['is_read' => true]);

            // Mark all messages in chat as read
            ChatMessage::where('chat_id', $chatId)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return ResponseHelper::success([
                'chat_id' => $chat->id,
                'is_read' => true,
                'updated_at' => $chat->updated_at,
            ], 'Chat marked as read successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update chat status
     */
    public function updateChatStatus(Request $request, $chatId)
    {
        try {
            $request->validate([
                'status' => 'required|in:open,closed,resolved',
                'type' => 'nullable|in:general,support,dispute',
            ]);

            $chat = Chat::findOrFail($chatId);
            
            $chat->update([
                'status' => $request->status,
                'type' => $request->get('type', $chat->type),
            ]);

            return ResponseHelper::success([
                'chat_id' => $chat->id,
                'status' => $chat->status,
                'type' => $chat->type,
                'updated_at' => $chat->updated_at,
            ], 'Chat status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete chat
     */
    public function deleteChat($chatId)
    {
        try {
            $chat = Chat::findOrFail($chatId);
            
            // Delete all messages first
            $chat->messages()->delete();
            $chat->delete();

            return ResponseHelper::success(null, 'Chat deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get chat analytics
     */
    public function getChatAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Chat trends
            $chatTrends = Chat::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_chats,
                SUM(CASE WHEN type = "dispute" THEN 1 ELSE 0 END) as dispute_chats,
                SUM(CASE WHEN type = "support" THEN 1 ELSE 0 END) as support_chats,
                SUM(CASE WHEN type = "general" THEN 1 ELSE 0 END) as general_chats
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Message statistics
            $messageStats = ChatMessage::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_messages,
                SUM(CASE WHEN sender_type = "buyer" THEN 1 ELSE 0 END) as user_messages,
                SUM(CASE WHEN sender_type = "store" THEN 1 ELSE 0 END) as admin_messages
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            return ResponseHelper::success([
                'chat_trends' => $chatTrends,
                'message_stats' => $messageStats,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format chats data for response
     */
    private function formatChatsData($chats)
    {
        return $chats->map(function ($chat) {
            $lastMessage = $chat->messages->first();
            
            return [
                'id' => $chat->id,
                'type' => $chat->type,
                'status' => $chat->status ?? 'active',
                'is_read' => $chat->is_read ?? false,
                'last_message' => $lastMessage ? $lastMessage->message : null,
                'last_message_sender' => $lastMessage ? $lastMessage->sender_type : null,
                'last_message_time' => $lastMessage ? $lastMessage->created_at : null,
                'formatted_date' => $chat->created_at->format('d-m-Y H:i A'),
                'created_at' => $chat->created_at,
                
                // Store/Seller information
                'store_info' => [
                    'store_id' => $chat->store->id,
                    'store_name' => $chat->store->store_name,
                    'store_email' => $chat->store->store_email,
                    'store_phone' => $chat->store->store_phone,
                    'store_location' => $chat->store->store_location,
                    'seller_name' => $chat->store->user->full_name,
                    'seller_email' => $chat->store->user->email,
                    'seller_phone' => $chat->store->user->phone,
                ],
                
                // Customer information
                'customer_info' => [
                    'customer_id' => $chat->user->id,
                    'customer_name' => $chat->user->full_name,
                    'customer_email' => $chat->user->email,
                    'customer_phone' => $chat->user->phone,
                    'customer_profile' => $chat->user->profile_picture ? asset('storage/' . $chat->user->profile_picture) : null,
                ],
            ];
        });
    }
}
