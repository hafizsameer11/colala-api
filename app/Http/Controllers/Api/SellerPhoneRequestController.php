<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\RevealPhone;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SellerPhoneRequestController extends Controller
{
    /**
     * Get all phone requests for seller's stores
     */
    public function getPendingRequests()
    {
        try {
            $userId = Auth::id();

            // Get all stores owned by this user
            $stores = Store::where('user_id', $userId)->pluck('id');

            // Get all pending phone requests
            $requests = RevealPhone::with(['chat.user', 'store'])
                ->whereIn('store_id', $stores)
                ->where('is_revealed', false)
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedRequests = $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'chat_id' => $request->chat_id,
                    'buyer' => [
                        'id' => $request->user_id,
                        'name' => $request->chat->user->full_name ?? 'Unknown',
                        'email' => $request->chat->user->email ?? null,
                        'profile_picture' => $request->chat->user->profile_picture 
                            ? asset('storage/' . $request->chat->user->profile_picture) 
                            : null,
                    ],
                    'store' => [
                        'id' => $request->store_id,
                        'name' => $request->store->store_name ?? null,
                    ],
                    'is_revealed' => $request->is_revealed,
                    'requested_at' => $request->created_at ? $request->created_at->format('d-m-Y H:i A') : null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $formattedRequests->count(),
                    'requests' => $formattedRequests,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get pending phone requests error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get phone requests',
            ], 500);
        }
    }

    /**
     * Approve phone number request
     */
    public function approvePhoneRequest(Request $request, $revealPhoneId)
    {
        try {
            $userId = Auth::id();

            // Find the reveal phone request
            $revealPhone = RevealPhone::with(['chat', 'store', 'user'])
                ->find($revealPhoneId);

            if (!$revealPhone) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phone request not found',
                ], 404);
            }

            // Verify the seller owns this store
            if ($revealPhone->store->user_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This request is not for your store.',
                ], 403);
            }

            // Check if already approved
            if ($revealPhone->is_revealed) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Phone number already shared',
                ]);
            }

            DB::beginTransaction();

            // 1. Update reveal phone status
            $revealPhone->update([
                'is_revealed' => true,
            ]);

            // 2. Send message to buyer with phone number
            $storeName = $revealPhone->store->store_name ?? 'Store';
            $storePhone = $revealPhone->store->store_phone ?? 'Not available';

            ChatMessage::create([
                'chat_id' => $revealPhone->chat_id,
                'sender_id' => $revealPhone->store->user_id,
                'sender_type' => 'store',
                'message' => "✅ Phone number approved! You can reach us at: {$storePhone}",
                'is_read' => false,
                'meta' => json_encode([
                    'type' => 'phone_approved',
                    'phone_number' => $storePhone,
                    'reveal_phone_id' => $revealPhone->id,
                ]),
            ]);

            // 3. Send confirmation message to seller
            ChatMessage::create([
                'chat_id' => $revealPhone->chat_id,
                'sender_id' => $userId,
                'sender_type' => 'store',
                'message' => "You have approved the phone number request.",
                'is_read' => true,
            ]);

            // Update chat last message
            $revealPhone->chat->update([
                'last_message' => 'Phone number shared',
                'last_message_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Phone number shared successfully',
                'data' => [
                    'chat_id' => $revealPhone->chat_id,
                    'phone_number' => $storePhone,
                    'is_revealed' => true,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve phone request error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve phone request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Decline phone number request
     */
    public function declinePhoneRequest(Request $request, $revealPhoneId)
    {
        try {
            $userId = Auth::id();

            // Find the reveal phone request
            $revealPhone = RevealPhone::with(['chat', 'store', 'user'])
                ->find($revealPhoneId);

            if (!$revealPhone) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phone request not found',
                ], 404);
            }

            // Verify the seller owns this store
            if ($revealPhone->store->user_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. This request is not for your store.',
                ], 403);
            }

            DB::beginTransaction();

            // 1. Delete the reveal phone request
            $chatId = $revealPhone->chat_id;
            $revealPhone->delete();

            // 2. Send message to buyer
            $storeName = $revealPhone->store->store_name ?? 'Store';

            ChatMessage::create([
                'chat_id' => $chatId,
                'sender_id' => $revealPhone->store->user_id,
                'sender_type' => 'store',
                'message' => "❌ Sorry, we cannot share our phone number at this time. Please continue chatting here.",
                'is_read' => false,
                'meta' => json_encode([
                    'type' => 'phone_declined',
                    'reveal_phone_id' => $revealPhoneId,
                ]),
            ]);

            // 3. Send confirmation message to seller
            ChatMessage::create([
                'chat_id' => $chatId,
                'sender_id' => $userId,
                'sender_type' => 'store',
                'message' => "You have declined the phone number request.",
                'is_read' => true,
            ]);

            // Update chat last message
            Chat::find($chatId)->update([
                'last_message' => 'Phone request declined',
                'last_message_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Phone number request declined',
                'data' => [
                    'chat_id' => $chatId,
                    'is_revealed' => false,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Decline phone request error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to decline phone request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

