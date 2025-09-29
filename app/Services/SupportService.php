<?php 

namespace App\Services;
use App\Models\{SupportTicket, SupportMessage, User, Order, StoreOrder};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

class SupportService {
    public function createTicket(array $data, int $userId): SupportTicket {
        return SupportTicket::create([
            'user_id'=>$userId,
            'category'=>$data['category'],
            'subject'=>$data['subject'],
            'description'=>$data['description'] ?? null,
            'order_id'=>$data['order_id'] ?? null,
            'store_order_id'=>$data['store_order_id'] ?? null,
        ]);
    }

    public function sendMessage(array $data, int $userId): SupportMessage {
        $attachmentPath = null;
        if (!empty($data['attachment'])) {
            $attachmentPath = $data['attachment']->store('support','public');
        }

        return SupportMessage::create([
            'ticket_id'=>$data['ticket_id'],
            'sender_id'=>$userId,
            'message'=>$data['message'] ?? null,
            'attachment'=>$attachmentPath,
        ]);
    }

    public function getTicketWithMessages(int $ticketId) {
        return SupportTicket::with(['messages.sender','user'])
            ->findOrFail($ticketId);
    }

    public function listTickets(int $userId) {
        return SupportTicket::where('user_id',$userId)
        ->with(['lastMessage'])->withCount('unreadMessagesCount')
            ->latest()
            ->get();
    }

    public function markMessagesRead(int $ticketId, int $userId) {
        SupportMessage::where('ticket_id',$ticketId)
            ->where('sender_id','!=',$userId)
            ->update(['is_read'=>true]);
    }
}