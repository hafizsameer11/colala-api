<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\Store;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Service;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class SellerChatController extends Controller
{
    /**
     * Get all chats for a specific seller
     */
    public function getSellerChats(Request $request, $userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $query = Chat::with(['user', 'lastMessage', 'service', 'dispute'])
                ->where('store_id', $store->id);

            // Filter by chat type
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Search by customer name or service name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('full_name', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('service', function ($serviceQuery) use ($search) {
                        $serviceQuery->where('title', 'like', "%{$search}%");
                    });
                });
            }

            $chats = $query->latest()->paginate(20);

            $chats->getCollection()->transform(function ($chat) {
                $unread = $chat->messages()->where('is_read', false)->count();
                return [
                    'id' => $chat->id,
                    'type' => $chat->type, // general | service | order
                    'customer_name' => $chat->user?->full_name,
                    'customer_email' => $chat->user?->email,
                    'customer_phone' => $chat->user?->phone,
                    'customer_avatar' => $chat->user?->profile_picture ? asset('storage/' . $chat->user->profile_picture) : null,
                    'service_name' => $chat->service?->title,
                    'last_message' => $chat->lastMessage?->message,
                    'last_message_at' => $chat->lastMessage?->created_at?->format('d-m-Y H:i:s'),
                    'unread_count' => $unread,
                    'has_dispute' => $chat->dispute ? true : false,
                    'dispute_status' => $chat->dispute?->status,
                    'created_at' => $chat->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $chat->updated_at->format('d-m-Y H:i:s')
                ];
            });

            // Get summary statistics
            $totalChats = Chat::where('store_id', $store->id)->count();
            $unreadChats = Chat::where('store_id', $store->id)
                ->whereHas('messages', function ($q) {
                    $q->where('is_read', false);
                })->count();
            $disputeChats = Chat::where('store_id', $store->id)
                ->whereHas('dispute')
                ->count();

            return ResponseHelper::success([
                'chats' => $chats,
                'summary_stats' => [
                    'total_chats' => $totalChats,
                    'unread_chats' => $unreadChats,
                    'dispute_chats' => $disputeChats
                ]
            ], 'Seller chats retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed chat information with messages
     */
    public function getChatDetails(Request $request, $userId, $chatId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $chat = Chat::with([
                'user',
                'service',
                'dispute',
                'messages.sender'
            ])->where('id', $chatId)
              ->where('store_id', $store->id)
              ->firstOrFail();

            // Mark buyer messages as read
            $chat->messages()->where('sender_type', 'buyer')->update(['is_read' => true]);

            $messages = $chat->messages()
                ->with('sender')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'image' => $message->image ? asset('storage/' . $message->image) : null,
                        'sender_type' => $message->sender_type,
                        'sender_name' => $message->sender?->full_name ?? ($message->sender_type === 'store' ? 'Store' : 'Customer'),
                        'sender_avatar' => $message->sender?->profile_picture ? asset('storage/' . $message->sender->profile_picture) : null,
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at->format('d-m-Y H:i:s'),
                        'updated_at' => $message->updated_at->format('d-m-Y H:i:s')
                    ];
                });

            $chatDetails = [
                'chat_info' => [
                    'id' => $chat->id,
                    'type' => $chat->type,
                    'created_at' => $chat->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $chat->updated_at->format('d-m-Y H:i:s')
                ],
                'customer_info' => [
                    'id' => $chat->user->id,
                    'name' => $chat->user->full_name,
                    'email' => $chat->user->email,
                    'phone' => $chat->user->phone,
                    'avatar' => $chat->user->profile_picture ? asset('storage/' . $chat->user->profile_picture) : null,
                    'is_verified' => $chat->user->is_active
                ],
                'service_info' => $chat->service ? [
                    'id' => $chat->service->id,
                    'title' => $chat->service->title,
                    'description' => $chat->service->description,
                    'price' => $chat->service->price ? 'N' . number_format($chat->service->price, 0) : null,
                    'status' => $chat->service->status
                ] : null,
                'dispute_info' => $chat->dispute ? [
                    'id' => $chat->dispute->id,
                    'status' => $chat->dispute->status,
                    'reason' => $chat->dispute->reason,
                    'description' => $chat->dispute->description,
                    'created_at' => $chat->dispute->created_at->format('d-m-Y H:i:s')
                ] : null,
                'messages' => $messages,
                'message_count' => $messages->count(),
                'unread_count' => $chat->messages()->where('is_read', false)->count()
            ];

            return ResponseHelper::success($chatDetails, 'Chat details retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send message as store (admin acting on behalf of store)
     */
    public function sendMessage(Request $request, $userId, $chatId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $chat = Chat::where('id', $chatId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $path = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('chat_images', 'public');
            }

            $message = ChatMessage::create([
                'chat_id' => $chat->id,
                'sender_id' => $userId,
                'sender_type' => 'store',
                'message' => $request->message,
                'image' => $path,
                'is_read' => false
            ]);

            return ResponseHelper::success([
                'message' => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'image' => $message->image ? asset('storage/' . $message->image) : null,
                    'sender_type' => $message->sender_type,
                    'sender_name' => 'Store',
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->format('d-m-Y H:i:s')
                ]
            ], 'Message sent successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark chat messages as read
     */
    public function markAsRead(Request $request, $userId, $chatId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $chat = Chat::where('id', $chatId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $chat->messages()->where('sender_type', 'buyer')->update(['is_read' => true]);

            return ResponseHelper::success([
                'chat_id' => $chat->id,
                'unread_count' => 0
            ], 'Messages marked as read successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get chat statistics for seller
     */
    public function getChatStatistics($userId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $totalChats = Chat::where('store_id', $store->id)->count();
            $generalChats = Chat::where('store_id', $store->id)->where('type', 'general')->count();
            $serviceChats = Chat::where('store_id', $store->id)->where('type', 'service')->count();
            $orderChats = Chat::where('store_id', $store->id)->where('type', 'order')->count();
            $unreadChats = Chat::where('store_id', $store->id)
                ->whereHas('messages', function ($q) {
                    $q->where('is_read', false);
                })->count();
            $disputeChats = Chat::where('store_id', $store->id)
                ->whereHas('dispute')
                ->count();

            $totalMessages = ChatMessage::whereHas('chat', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->count();

            $storeMessages = ChatMessage::whereHas('chat', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->where('sender_type', 'store')->count();

            $customerMessages = ChatMessage::whereHas('chat', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->where('sender_type', 'buyer')->count();

            return ResponseHelper::success([
                'chat_counts' => [
                    'total' => $totalChats,
                    'general' => $generalChats,
                    'service' => $serviceChats,
                    'order' => $orderChats,
                    'unread' => $unreadChats,
                    'dispute' => $disputeChats
                ],
                'message_counts' => [
                    'total' => $totalMessages,
                    'store' => $storeMessages,
                    'customer' => $customerMessages
                ],
                'response_rate' => $totalMessages > 0 ? round(($storeMessages / $totalMessages) * 100, 2) : 0
            ], 'Chat statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Create a new chat (admin can initiate chat)
     */
    public function createChat(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer_id' => 'required|exists:users,id',
                'type' => 'required|string|in:general,service,order',
                'service_id' => 'nullable|exists:services,id',
                'order_id' => 'nullable|exists:orders,id'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            // Check if chat already exists
            $existingChat = Chat::where('store_id', $store->id)
                ->where('user_id', $request->customer_id)
                ->where('type', $request->type)
                ->first();

            if ($existingChat) {
                return ResponseHelper::error('Chat already exists for this customer and type', 400);
            }

            $chat = Chat::create([
                'store_id' => $store->id,
                'user_id' => $request->customer_id,
                'type' => $request->type,
                'service_id' => $request->service_id,
                'order_id' => $request->order_id
            ]);

            return ResponseHelper::success([
                'chat' => [
                    'id' => $chat->id,
                    'type' => $chat->type,
                    'customer_id' => $chat->user_id,
                    'service_id' => $chat->service_id,
                    'order_id' => $chat->order_id,
                    'created_at' => $chat->created_at->format('d-m-Y H:i:s')
                ]
            ], 'Chat created successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete a chat
     */
    public function deleteChat($userId, $chatId)
    {
        try {
            $user = User::where('role', 'seller')->findOrFail($userId);
            $store = $user->store;

            if (!$store) {
                return ResponseHelper::error('No store found for this seller', 404);
            }

            $chat = Chat::where('id', $chatId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            // Delete all messages first
            $chat->messages()->delete();
            $chat->delete();

            return ResponseHelper::success(null, 'Chat deleted successfully');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
