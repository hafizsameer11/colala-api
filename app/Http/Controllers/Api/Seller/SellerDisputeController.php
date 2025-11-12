<?php

namespace App\Http\Controllers\Api\Seller;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{Dispute, DisputeChat, DisputeChatMessage, Store, StoreOrder};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class SellerDisputeController extends Controller
{
    /**
     * List all disputes for logged-in seller's store
     */
    public function myDisputes(Request $request)
    {
        try {
            $seller = $request->user();
            
            // Get seller's store
            $store = Store::where('user_id', $seller->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller.', 404);
            }

            $disputes = Dispute::with([
                'disputeChat.buyer',
                'disputeChat.seller',
                'disputeChat.store',
                'disputeChat.lastMessage',
                'storeOrder.store'
            ])
                ->whereHas('disputeChat', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->latest()
                ->get();

            $formattedDisputes = $disputes->map(function ($dispute) {
                return [
                    'id' => $dispute->id,
                    'category' => $dispute->category,
                    'details' => $dispute->details,
                    'images' => $dispute->images,
                    'status' => $dispute->status,
                    'won_by' => $dispute->won_by,
                    'resolution_notes' => $dispute->resolution_notes,
                    'created_at' => $dispute->created_at,
                    'buyer' => $dispute->disputeChat && $dispute->disputeChat->buyer ? [
                        'id' => $dispute->disputeChat->buyer->id,
                        'name' => $dispute->disputeChat->buyer->full_name ?? $dispute->disputeChat->buyer->first_name . ' ' . $dispute->disputeChat->buyer->last_name,
                        'email' => $dispute->disputeChat->buyer->email,
                    ] : null,
                    'store_order' => $dispute->storeOrder ? [
                        'id' => $dispute->storeOrder->id,
                        'status' => $dispute->storeOrder->status,
                    ] : null,
                    'last_message' => $dispute->disputeChat && $dispute->disputeChat->lastMessage ? [
                        'message' => $dispute->disputeChat->lastMessage->message,
                        'sender_type' => $dispute->disputeChat->lastMessage->sender_type,
                        'created_at' => $dispute->disputeChat->lastMessage->created_at,
                    ] : null,
                ];
            });

            return ResponseHelper::success($formattedDisputes);

        } catch (Exception $e) {
            Log::error('Error fetching seller disputes: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * View a single dispute with full chat messages (Seller)
     */
    public function show($id)
    {
        try {
            $seller = request()->user();
            
            // Get seller's store
            $store = Store::where('user_id', $seller->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller.', 404);
            }

            $dispute = Dispute::with([
                'disputeChat.buyer',
                'disputeChat.seller',
                'disputeChat.store',
                'disputeChat.messages.sender',
                'storeOrder.store',
                'storeOrder.items'
            ])
                ->whereHas('disputeChat', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->findOrFail($id);

            return ResponseHelper::success([
                'dispute' => [
                    'id' => $dispute->id,
                    'category' => $dispute->category,
                    'details' => $dispute->details,
                    'images' => $dispute->images,
                    'status' => $dispute->status,
                    'won_by' => $dispute->won_by,
                    'resolution_notes' => $dispute->resolution_notes,
                    'created_at' => $dispute->created_at,
                    'resolved_at' => $dispute->resolved_at,
                    'closed_at' => $dispute->closed_at,
                ],
                'dispute_chat' => [
                    'id' => $dispute->disputeChat->id,
                    'buyer' => [
                        'id' => $dispute->disputeChat->buyer->id,
                        'name' => $dispute->disputeChat->buyer->full_name ?? $dispute->disputeChat->buyer->first_name . ' ' . $dispute->disputeChat->buyer->last_name,
                        'email' => $dispute->disputeChat->buyer->email,
                    ],
                    'seller' => [
                        'id' => $dispute->disputeChat->seller->id,
                        'name' => $dispute->disputeChat->seller->full_name ?? $dispute->disputeChat->seller->first_name . ' ' . $dispute->disputeChat->seller->last_name,
                        'email' => $dispute->disputeChat->seller->email,
                    ],
                    'store' => [
                        'id' => $dispute->disputeChat->store->id,
                        'name' => $dispute->disputeChat->store->store_name,
                    ],
                    'messages' => $dispute->disputeChat->messages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'sender_id' => $message->sender_id,
                            'sender_type' => $message->sender_type,
                            'sender_name' => $message->sender->full_name ?? $message->sender->first_name . ' ' . $message->sender->last_name,
                            'message' => $message->message,
                            'image' => $message->image ? asset('storage/' . $message->image) : null,
                            'is_read' => $message->is_read,
                            'created_at' => $message->created_at,
                        ];
                    }),
                ],
                'store_order' => $dispute->storeOrder,
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching seller dispute: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send message in dispute chat (Seller)
     */
    public function sendMessage(Request $request, $disputeId)
    {
        try {
            $request->validate([
                'message' => 'nullable|string|max:5000',
                'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $seller = $request->user();
            
            // Get seller's store
            $store = Store::where('user_id', $seller->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller.', 404);
            }

            // Verify dispute belongs to seller's store
            $dispute = Dispute::with('disputeChat')
                ->whereHas('disputeChat', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->findOrFail($disputeId);

            if (!$dispute->disputeChat) {
                return ResponseHelper::error('Dispute chat not found.', 404);
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('dispute_chat_images', 'public');
            }

            if (!$request->message && !$imagePath) {
                return ResponseHelper::error('Message or image is required.', 422);
            }

            $message = DisputeChatMessage::create([
                'dispute_chat_id' => $dispute->disputeChat->id,
                'sender_id' => $seller->id,
                'sender_type' => 'seller',
                'message' => $request->message,
                'image' => $imagePath,
                'is_read' => false,
            ]);

            // Mark buyer and admin messages as read for this seller
            $dispute->disputeChat->messages()
                ->where('sender_type', '!=', 'seller')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return ResponseHelper::success([
                'message' => $message->load('sender')
            ], 'Message sent successfully.');

        } catch (Exception $e) {
            Log::error('Error sending seller message: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark messages as read (Seller)
     */
    public function markAsRead(Request $request, $disputeId)
    {
        try {
            $seller = $request->user();
            
            // Get seller's store
            $store = Store::where('user_id', $seller->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found for this seller.', 404);
            }

            $dispute = Dispute::with('disputeChat')
                ->whereHas('disputeChat', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->findOrFail($disputeId);

            if (!$dispute->disputeChat) {
                return ResponseHelper::error('Dispute chat not found.', 404);
            }

            // Mark all non-seller messages as read
            $dispute->disputeChat->messages()
                ->where('sender_type', '!=', 'seller')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return ResponseHelper::success([], 'Messages marked as read.');

        } catch (Exception $e) {
            Log::error('Error marking seller messages as read: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
