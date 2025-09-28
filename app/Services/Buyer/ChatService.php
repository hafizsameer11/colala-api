<?php 

namespace App\Services\Buyer;

use App\Models\{Chat, ChatMessage, StoreOrder};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatService {
    public function getOrCreateChat(int $storeOrderId, int $userId, int $storeId): Chat {
        return Chat::firstOrCreate([
            'store_order_id' => $storeOrderId,
            'user_id' => $userId,
            'store_id' => $storeId,
        ]);
    }

    public function fetchChatList(int $userId) {
        return Chat::with(['store','lastMessage','service'])
            ->where('user_id',$userId)
            ->get()
            ->map(function($chat){
                $unread = $chat->messages()->where('is_read',false)->count();
                return [
                    'chat_id'=>$chat->id,
                    'store'=>$chat->store->store_name,
                    'last_message'=>$chat->lastMessage?->message,
                    'last_message_at'=>$chat->lastMessage?->created_at,
                    'unread_count'=>$unread,
                    'avatar'=>$chat->store->profile_image ? asset('storage/'.$chat->store->profile_image) : null,
                ];
            });
    }

    public function fetchMessages(int $chatId, int $userId) {
        $chat = Chat::with('store')->where('id',$chatId)->where('user_id',$userId)->firstOrFail();
        // mark messages as read
        $chat->messages()->where('sender_type','store')->update(['is_read'=>true]);
        return $chat->messages()->with('sender')->orderBy('created_at','asc')->get();
    }

    public function sendMessage(int $chatId, int $senderId, string $senderType, ?string $text, $imageFile = null) {
        $chat = Chat::findOrFail($chatId);

        $path = null;
        if ($imageFile) {
            $path = $imageFile->store('chat_images','public');
        }

        return ChatMessage::create([
            'chat_id'=>$chat->id,
            'sender_id'=>$senderId,
            'sender_type'=>$senderType,
            'message'=>$text,
            'image'=>$path,
            'is_read'=>false
        ]);
    }
    public function startChatWithStore(int $userId, int $storeId): Chat {
        // Check if a general chat already exists between the user and store
        $existingChat = Chat::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->whereNull('store_order_id')
            ->where('type', 'general')
            ->first();

        if ($existingChat) {
            return $existingChat;
        }

        // Create a new general chat
        return Chat::create([
            'user_id' => $userId,
            'store_id' => $storeId,
            'type' => 'general',
            'store_order_id' => null,
            'service_id' => null,
        ]);
    }
    public function startChatForService(int $userId, int $storeId, int $serviceId): Chat {
        // Check if a service chat already exists between the user and store for the given service
        $existingChat = Chat::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('service_id', $serviceId)
            ->where('type', 'service')
            ->first();

        if ($existingChat) {
            return $existingChat;
        }

        // Create a new service chat
        return Chat::create([
            'user_id' => $userId,
            'store_id' => $storeId,
            'service_id' => $serviceId,
            'type' => 'service',
            'store_order_id' => null,
        ]);
    }
}
