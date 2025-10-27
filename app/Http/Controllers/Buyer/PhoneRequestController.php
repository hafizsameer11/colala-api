<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\RevealPhone;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PhoneRequestController extends Controller
{
    /**
     * Request phone number from seller
     * Buyer clicks "Request Phone Number" button
     */
    public function requestPhoneNumber(Request $request)
    {
        try {
            $request->validate([
                'store_id' => 'required|exists:stores,id',
            ]);

            $buyerId = Auth::id();
            $storeId = $request->store_id;

            // Get the store
            $store = Store::find($storeId);
            if (!$store) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found',
                ], 404);
            }

            DB::beginTransaction();

            // 1. Check if chat exists (any type: product or service)
            $chat = Chat::where('user_id', $buyerId)
                ->where('store_id', $storeId)
                ->first();

            // 2. If no chat, create one
            if (!$chat) {
                $chat = Chat::create([
                    'user_id' => $buyerId,
                    'store_id' => $storeId,
                    'product_id' => null, // General chat
                    'service_id' => null,
                    'last_message' => 'Phone number request',
                    'last_message_at' => now(),
                ]);
            }

            // 3. Check if phone number was already requested
            $existingRequest = RevealPhone::where('chat_id', $chat->id)
                ->where('user_id', $buyerId)
                ->where('store_id', $storeId)
                ->first();

            if ($existingRequest) {
                if ($existingRequest->is_revealed) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Phone number already shared',
                        'data' => [
                            'chat_id' => $chat->id,
                            'is_revealed' => true,
                            'phone_number' => $store->store_phone,
                        ],
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Phone number request already pending',
                    'data' => [
                        'chat_id' => $chat->id,
                        'is_revealed' => false,
                    ],
                ]);
            }

            // 4. Create reveal phone request
            $revealPhone = RevealPhone::create([
                'chat_id' => $chat->id,
                'user_id' => $buyerId,
                'store_id' => $storeId,
                'is_revealed' => false,
            ]);

            // 5. Send message to seller (from buyer)
            $buyerName = Auth::user()->full_name ?? 'A buyer';
            ChatMessage::create([
                'chat_id' => $chat->id,
                'sender_id' => $buyerId,
                'sender_type' => 'user',
                'message' => "I would like to request your phone number to discuss further.",
                'is_read' => false,
            ]);

            // 6. Send system message to seller (notification style)
            ChatMessage::create([
                'chat_id' => $chat->id,
                'sender_id' => $store->user_id,
                'sender_type' => 'store',
                'message' => "📞 {$buyerName} has requested your phone number. [APPROVE] or [DECLINE]",
                'is_read' => false,
                'meta' => json_encode([
                    'type' => 'phone_request',
                    'reveal_phone_id' => $revealPhone->id,
                    'status' => 'pending',
                ]),
            ]);

            // Update chat last message
            $chat->update([
                'last_message' => 'Phone number requested',
                'last_message_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Phone number request sent successfully',
                'data' => [
                    'chat_id' => $chat->id,
                    'reveal_phone_id' => $revealPhone->id,
                    'is_revealed' => false,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Phone request error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send phone request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check phone request status
     */
    public function checkPhoneRequestStatus(Request $request)
    {
        try {
            $request->validate([
                'store_id' => 'required|exists:stores,id',
            ]);

            $buyerId = Auth::id();
            $storeId = $request->store_id;

            // Find the chat
            $chat = Chat::where('user_id', $buyerId)
                ->where('store_id', $storeId)
                ->first();

            if (!$chat) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_request' => false,
                        'is_revealed' => false,
                    ],
                ]);
            }

            // Find the reveal phone request
            $revealPhone = RevealPhone::where('chat_id', $chat->id)
                ->where('user_id', $buyerId)
                ->where('store_id', $storeId)
                ->first();

            if (!$revealPhone) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_request' => false,
                        'is_revealed' => false,
                        'chat_id' => $chat->id,
                    ],
                ]);
            }

            $response = [
                'has_request' => true,
                'is_revealed' => $revealPhone->is_revealed,
                'chat_id' => $chat->id,
            ];

            // If revealed, include phone number
            if ($revealPhone->is_revealed) {
                $store = Store::find($storeId);
                $response['phone_number'] = $store->store_phone;
            }

            return response()->json([
                'status' => 'success',
                'data' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error('Check phone status error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check phone request status',
            ], 500);
        }
    }
}

