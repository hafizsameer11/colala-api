<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Services\SellerChatService;
// use App\Services\Seller\SellerChatService;
use Exception;
use Illuminate\Http\Request;

class SellerChatController extends Controller
{
    public function __construct(private SellerChatService $svc) {}

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
            //check atelast message or image must be present
            if(!$req->has('message') && !$req->hasFile('image')) {
                return ResponseHelper::error('At least one message or image must be present', 400);
            }
            $msg = $this->svc->sendMessage(
                (int)$chatId,
                $req->user()->id,
                $req->input('message'),
                $req->file('image')
            );
            return ResponseHelper::success($msg, 'Message sent');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get unread message count for seller
     */
    public function unreadCount(Request $req)
    {
        try {
            $sellerId = $req->user()->id;
            
            // Get seller's store
            $store = \App\Models\Store::where('user_id', $sellerId)->first();
            
            if (!$store) {
                // Check if user is a store user
                $storeUser = \App\Models\StoreUser::where('user_id', $sellerId)->first();
                if ($storeUser) {
                    $store = $storeUser->store;
                }
            }
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller', 404);
            }

            // Count unread messages from buyers in regular chats
            $regularChatUnread = \App\Models\ChatMessage::whereHas('chat', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })
            ->where('sender_type', 'buyer')
            ->where('is_read', false)
            ->count();

            // Count unread messages from buyers and admins in dispute chats
            $disputeChatUnread = \App\Models\DisputeChatMessage::whereHas('disputeChat', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })
            ->whereIn('sender_type', ['buyer', 'admin'])
            ->where('is_read', false)
            ->count();

            $totalUnread = $regularChatUnread + $disputeChatUnread;
            $pendingOrders = \App\Models\StoreOrder::where('store_id', $store->id)->where('status','pending_acceptance')->count();// status can also be pending_acceptance
            
            // Count unread notifications for the seller
            $unreadNotifications = \App\Models\UserNotification::where('user_id', $sellerId)
                ->where('is_read', false)
                ->count();
            
            return ResponseHelper::success([
                'total_unread' => $totalUnread,
                'regular_chat_unread' => $regularChatUnread,
                'dispute_chat_unread' => $disputeChatUnread,
                'pending_orders' => $pendingOrders,
                'unread_notifications' => $unreadNotifications,
            ], 'Unread message count retrieved successfully');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
