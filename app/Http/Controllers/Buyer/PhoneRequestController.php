<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\RevealPhone;
use App\Models\Store;
use App\Models\UserNotification;
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
            $store = Store::with('user')->find($storeId);
            if (!$store) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found',
                ], 404);
            }

            DB::beginTransaction();

            // Check if phone number was already requested
            $existingRequest = RevealPhone::where('user_id', $buyerId)
                ->where('store_id', $storeId)
                ->first();

            if ($existingRequest) {
                if ($existingRequest->is_revealed) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Phone number already shared',
                        'data' => [
                            'is_revealed' => true,
                            'phone_number' => $store->store_phone,
                        ],
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Phone number request already pending',
                    'data' => [
                        'is_revealed' => false,
                    ],
                ]);
            }

            // Create reveal phone request
            $revealPhone = RevealPhone::create([
                'user_id' => $buyerId,
                'store_id' => $storeId,
                'is_revealed' => false,
            ]);

            // Get buyer name
            $buyerName = Auth::user()->full_name ?? 'A buyer';

            // Create notification for seller
            if ($store->user) {
                UserNotification::create([
                    'user_id' => $store->user_id,
                    'title' => 'Phone Number Request',
                    'content' => "{$buyerName} has requested your phone number for {$store->store_name}. Request ID: {$revealPhone->id}",
                    'is_read' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Phone number request sent successfully',
                'data' => [
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

            // Find the reveal phone request
            $revealPhone = RevealPhone::where('user_id', $buyerId)
                ->where('store_id', $storeId)
                ->first();

            if (!$revealPhone) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'has_request' => false,
                        'is_revealed' => false,
                    ],
                ]);
            }

            $response = [
                'has_request' => true,
                'is_revealed' => $revealPhone->is_revealed,
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

    /**
     * Get all phone requests (revealed phone numbers)
     */
    public function getRevealedPhoneNumbers()
    {
        try {
            $buyerId = Auth::id();

            $revealedPhones = RevealPhone::with('store')
                ->where('user_id', $buyerId)
                ->where('is_revealed', true)
                ->orderBy('updated_at', 'desc')
                ->get();

            $formattedPhones = $revealedPhones->map(function ($reveal) {
                return [
                    'id' => $reveal->id,
                    'store' => [
                        'id' => $reveal->store_id,
                        'name' => $reveal->store->store_name ?? null,
                        'phone_number' => $reveal->store->store_phone ?? null,
                        'profile_image' => $reveal->store->profile_image 
                            ? asset('storage/' . $reveal->store->profile_image) 
                            : null,
                    ],
                    'revealed_at' => $reveal->updated_at ? $reveal->updated_at->format('d-m-Y H:i A') : null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $formattedPhones->count(),
                    'phone_numbers' => $formattedPhones,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get revealed phone numbers error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get phone numbers',
            ], 500);
        }
    }
}
