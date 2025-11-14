<?php

namespace App\Http\Controllers\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Services\Buyer\ChatService;
use Exception;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $svc) {}

    public function list(Request $req)
    {
        try {
            $chats = $this->svc->fetchChatList($req->user()->id);
            return ResponseHelper::success($chats);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function messages(Request $req, $chatId)
    {
        try {
            $messages = $this->svc->fetchMessages((int)$chatId, $req->user()->id);
            return ResponseHelper::success($messages);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function send(SendMessageRequest $req, $chatId)
    {
        try {
            $msg = $this->svc->sendMessage(
                (int)$chatId,
                $req->user()->id,
                'buyer',
                $req->input('message'),
                $req->file('image')
            );
            return ResponseHelper::success($msg, 'Message sent');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function startChatWithStore(Request $req, $storeId)
    {
        try {
            $chat = $this->svc->startChatWithStore($req->user()->id, (int)$storeId);
            return ResponseHelper::success($chat, 'Chat started');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function startChatWithStoreForService(Request $req, $storeId)
    {
        try {
            $serviceId = $req->input('service_id');
            if (!$serviceId) {
                return ResponseHelper::error('service_id is required', 400);
            }
            $chat = $this->svc->startChatForService($req->user()->id, (int)$storeId, (int)$serviceId);
            return ResponseHelper::success($chat, 'Chat started');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function startChatWithCustomer($user_id){
        try{
        $chat = $this->svc->startChatWithCustomer($user_id);
            return ResponseHelper::success($chat, 'Chat started');

        }catch(Exception $e){
            return ResponseHelper::error($e->getMessage(),500);
        }
    }

    /**
     * Get unread message count for buyer
     */
    public function unreadCount(Request $req)
    {
        try {
            $userId = $req->user()->id;
            
            // Count unread messages from stores in regular chats
            $regularChatUnread = \App\Models\ChatMessage::whereHas('chat', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('sender_type', 'store')
            ->where('is_read', false)
            ->count();

            // Count unread messages from sellers and admins in dispute chats
            $disputeChatUnread = \App\Models\DisputeChatMessage::whereHas('disputeChat', function ($query) use ($userId) {
                $query->where('buyer_id', $userId);
            })
            ->whereIn('sender_type', ['seller', 'admin'])
            ->where('is_read', false)
            ->count();

            $totalUnread = $regularChatUnread + $disputeChatUnread;

            return ResponseHelper::success([
                'total_unread' => $totalUnread,
                'regular_chat_unread' => $regularChatUnread,
                'dispute_chat_unread' => $disputeChatUnread,
            ], 'Unread message count retrieved successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
