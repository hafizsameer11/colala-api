<?php

namespace App\Services;

use App\Models\{Chat, ChatMessage, Store};

class SellerChatService
{
    public function fetchChatList(int $sellerId)
    {
        $store = Store::where('user_id', $sellerId)->firstOrFail();

        return Chat::with(['user','lastMessage','service','store'])
            ->where('store_id', $store->id)
            ->get()
            ->map(function ($chat) {
                $unread = $chat->messages()->where('is_read', false)->count();
                return [
                    'chat_id'         => $chat->id,
                    'chat_type'       => $chat->type, // general | service | order
                    'user'            => $chat->user?->full_name,
                    'service'         => $chat->service?->title,
                    'last_message'    => $chat->lastMessage?->message,
                    'last_message_at' => $chat->lastMessage?->created_at,
                    'unread_count'    => $unread,
                    'profile_picture'   => $chat->user?->profile_picture ,
                    'avatar'          => $chat->user?->profile_picture 
                                          ? asset('storage/'.$chat->user->profile_picture) 
                                          : null,
                                          'user_id'         => $chat->user?->id ,
                                          'store_id'        => $chat->store?->id ,
                ];
            });
    }

    public function fetchMessages(int $chatId, int $sellerId)
    {
        $store = Store::where('user_id', $sellerId)->firstOrFail();

        $chat = Chat::with(['user','dispute'])
            ->where('id', $chatId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // mark buyer messages as read
        $chat->messages()->where('sender_type', 'buyer')->update(['is_read' => true]);

        return [
            'messages' => $chat->messages()->with('sender')->orderBy('created_at','asc')->get(),
            'user'     => $chat->user,
            'dispute'  => $chat->dispute,
        ];
    }

    public function sendMessage(int $chatId, int $sellerId, string $text, $imageFile = null)
    {
        $chat = Chat::findOrFail($chatId);
        $store = Store::where('user_id', $sellerId)->firstOrFail();

        if ($chat->store_id !== $store->id) {
            throw new \Exception("Unauthorized: Seller does not own this chat");
        }

        $path = null;
        if ($imageFile) {
            $path = $imageFile->store('chat_images','public');
        }

        return ChatMessage::create([
            'chat_id'     => $chat->id,
            'sender_id'   => $sellerId,
            'sender_type' => 'store',
            'message'     => $text,
            'image'       => $path,
            'is_read'     => false,
        ]);
    }
}
