<?php 

namespace App\Http\Controllers\Buyer;

use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDisputeRequest;
use App\Models\{Dispute, DisputeChat, DisputeChatMessage, StoreOrder, Store};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class DisputeController extends Controller
{
    /**
     * Create a new dispute with dispute chat
     */
    public function store(CreateDisputeRequest $request)
    {
        try {
            $data = $request->validated();
            $buyer = $request->user();

            // Get store order to find seller and store
            $storeOrder = StoreOrder::with(['store.user', 'order'])->findOrFail($data['store_order_id']);
            $store = $storeOrder->store;
            $seller = $store->user;

            if (!$seller) {
                return ResponseHelper::error('Seller not found for this store order.', 404);
            }

            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $img) {
                    $imagePaths[] = $img->store('disputes', 'public');
                }
            }

            // Create dispute chat first
            $disputeChat = DisputeChat::create([
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'store_id' => $store->id,
            ]);

            // Create dispute
            $dispute = Dispute::create([
                'chat_id' => $data['chat_id'] ?? null, // Keep for backward compatibility
                'dispute_chat_id' => $disputeChat->id,
                'store_order_id' => $data['store_order_id'],
                'user_id' => $buyer->id,
                'category' => $data['category'],
                'details' => $data['details'] ?? null,
                'images' => $imagePaths,
                'status' => 'open',
            ]);

            // Update dispute_id in dispute_chat
            $disputeChat->update(['dispute_id' => $dispute->id]);

            // Update store order status to 'disputed'
            $storeOrder->update(['status' => 'disputed']);

            // Create initial system message in dispute chat
            DisputeChatMessage::create([
                'dispute_chat_id' => $disputeChat->id,
                'sender_id' => $buyer->id,
                'sender_type' => 'buyer',
                'message' => "📌 Dispute created: {$data['category']}" . ($data['details'] ? "\n\n{$data['details']}" : ''),
                'is_read' => false,
            ]);

            // Notify seller about new dispute
            $orderNo = $storeOrder->order ? $storeOrder->order->order_no : 'N/A';
            if ($seller) {
                UserNotificationHelper::notify(
                    $seller->id,
                    'New Dispute Created',
                    "A dispute has been created for order #{$orderNo}. Category: {$data['category']}",
                    [
                        'type' => 'dispute_created',
                        'dispute_id' => $dispute->id,
                        'store_order_id' => $storeOrder->id,
                        'category' => $data['category'],
                        'buyer_id' => $buyer->id
                    ]
                );
            }

            // Notify buyer
            UserNotificationHelper::notify(
                $buyer->id,
                'Dispute Created',
                "Your dispute for order #{$orderNo} has been created and is under review.",
                [
                    'type' => 'dispute_created',
                    'dispute_id' => $dispute->id,
                    'store_order_id' => $storeOrder->id,
                    'category' => $data['category']
                ]
            );

            return ResponseHelper::success([
                'dispute' => $dispute->load('disputeChat.buyer', 'disputeChat.seller', 'disputeChat.store', 'storeOrder'),
                'dispute_chat' => $disputeChat
            ], 'Dispute created successfully.');

        } catch (Exception $e) {
            Log::error('Error creating dispute: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * List all disputes for logged-in buyer
     */
    public function myDisputes(Request $request)
    {
        try {
            $disputes = Dispute::with([
                'disputeChat.buyer',
                'disputeChat.seller',
                'disputeChat.store',
                'disputeChat.lastMessage',
                'storeOrder.store'
            ])
                ->where('user_id', $request->user()->id)
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
                    'store_order' => $dispute->storeOrder ? [
                        'id' => $dispute->storeOrder->id,
                        'status' => $dispute->storeOrder->status,
                    ] : null,
                    'store' => $dispute->disputeChat && $dispute->disputeChat->store ? [
                        'id' => $dispute->disputeChat->store->id,
                        'name' => $dispute->disputeChat->store->store_name,
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
            Log::error('Error fetching disputes: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * View a single dispute with full chat messages
     */
    public function show($id)
    {
        try {
            // Validate that id is numeric
            if (!is_numeric($id)) {
                return ResponseHelper::error('Invalid dispute ID. ID must be a number.', 422);
            }

            $buyer = request()->user();
            
            $dispute = Dispute::with([
                'disputeChat.buyer',
                'disputeChat.seller',
                'disputeChat.store',
                'disputeChat.messages.sender',
                'storeOrder.store',
                'storeOrder.items'
            ])
                ->where('user_id', $buyer->id)
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
            Log::error('Error fetching dispute: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Send message in dispute chat (Buyer)
     */
    public function sendMessage(Request $request, $disputeId)
    {
        try {
            // Validate that disputeId is numeric
            if (!is_numeric($disputeId)) {
                return ResponseHelper::error('Invalid dispute ID. ID must be a number.', 422);
            }

            $request->validate([
                'message' => 'nullable|string|max:5000',
                'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $buyer = $request->user();

            // Verify dispute belongs to buyer
            $dispute = Dispute::with('disputeChat')
                ->where('user_id', $buyer->id)
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
                'sender_id' => $buyer->id,
                'sender_type' => 'buyer',
                'message' => $request->message,
                'image' => $imagePath,
                'is_read' => false,
            ]);

            // Mark seller and admin messages as read for this buyer
            $dispute->disputeChat->messages()
                ->where('sender_type', '!=', 'buyer')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return ResponseHelper::success([
                'message' => $message->load('sender')
            ], 'Message sent successfully.');

        } catch (Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark messages as read (Buyer)
     */
    public function markAsRead(Request $request, $disputeId)
    {
        try {
            // Validate that disputeId is numeric
            if (!is_numeric($disputeId)) {
                return ResponseHelper::error('Invalid dispute ID. ID must be a number.', 422);
            }

            $buyer = $request->user();

            $dispute = Dispute::with('disputeChat')
                ->where('user_id', $buyer->id)
                ->findOrFail($disputeId);

            if (!$dispute->disputeChat) {
                return ResponseHelper::error('Dispute chat not found.', 404);
            }

            // Mark all non-buyer messages as read
            $dispute->disputeChat->messages()
                ->where('sender_type', '!=', 'buyer')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return ResponseHelper::success([], 'Messages marked as read.');

        } catch (Exception $e) {
            Log::error('Error marking messages as read: ' . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
