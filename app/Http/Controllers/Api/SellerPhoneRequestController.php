<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RevealPhone;
use App\Models\Store;
use App\Models\UserNotification;
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
            $requests = RevealPhone::with(['user', 'store'])
                ->whereIn('store_id', $stores)
                ->where('is_revealed', false)
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedRequests = $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'buyer' => [
                        'id' => $request->user_id,
                        'name' => $request->user->full_name ?? 'Unknown',
                        'email' => $request->user->email ?? null,
                        'profile_picture' => $request->user->profile_picture 
                            ? asset('storage/' . $request->user->profile_picture) 
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
            $revealPhone = RevealPhone::with(['store', 'user'])
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

            // 2. Create notification for buyer with phone number embedded in content
            $storeName = $revealPhone->store->store_name ?? 'Store';
            $storePhone = $revealPhone->store->store_phone ?? 'Not available';

            UserNotification::create([
                'user_id' => $revealPhone->user_id,
                'title' => 'Phone Number Approved',
                'content' => "{$storeName} has approved your phone number request. Phone: {$storePhone}",
                'is_read' => false,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Phone number shared successfully',
                'data' => [
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
            $revealPhone = RevealPhone::with(['store', 'user'])
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

            // 1. Create notification for buyer (declined)
            $storeName = $revealPhone->store->store_name ?? 'Store';

            UserNotification::create([
                'user_id' => $revealPhone->user_id,
                'title' => 'Phone Number Request Declined',
                'content' => "{$storeName} has declined your phone number request. Please contact them through chat.",
                'is_read' => false,
            ]);

            // 2. Delete the reveal phone request
            $revealPhone->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Phone number request declined',
                'data' => [
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
