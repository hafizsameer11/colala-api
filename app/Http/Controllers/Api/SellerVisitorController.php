<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{StoreVisitor, Store, Chat, StoreUser};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SellerVisitorController extends Controller
{
    /**
     * Get all visitors for the seller's store
     */
    public function getVisitors(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get seller's store
            $store = $user->store;
            //if cannot find store id it can bbe the other user not the owner so lets check in the store user table
            if (!$store) {
                $storeUser = StoreUser::where('user_id', $user->id)->first();
                if ($storeUser) {
                    $store = $storeUser->store;
                }
            }
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Query parameters
            $perPage = $request->input('per_page', 20);
            $visitType = $request->input('visit_type'); // 'store' or 'product'
            $search = $request->input('search');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // Build query
            $query = StoreVisitor::where('store_id', $store->id)
                ->with(['user', 'product:id,name,price,discount_price'])
                ->select([
                    'store_visitors.*',
                    DB::raw('MAX(store_visitors.created_at) as last_visit')
                ])
                ->groupBy('user_id', 'store_id', 'store_visitors.id');

            // Filter by visit type
            if ($visitType) {
                $query->where('visit_type', $visitType);
            }

            // Search by user details
            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Date range filter
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            // Order by most recent visit
            $query->orderBy('last_visit', 'desc');

            $visitors = $query->paginate($perPage);

            // Format response
            $formattedVisitors = $visitors->map(function ($visitor) use ($store) {
                return $this->formatVisitorData($visitor, $store);
            });

            // Get statistics
            $stats = $this->getVisitorStats($store->id, $dateFrom, $dateTo);

            return ResponseHelper::success([
                'visitors' => $formattedVisitors,
                'pagination' => [
                    'current_page' => $visitors->currentPage(),
                    'per_page' => $visitors->perPage(),
                    'total' => $visitors->total(),
                    'last_page' => $visitors->lastPage(),
                ],
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get visitor statistics
     */
    private function getVisitorStats($storeId, $dateFrom = null, $dateTo = null)
    {
        $query = StoreVisitor::where('store_id', $storeId);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $totalVisits = $query->count();
        $uniqueVisitors = $query->distinct('user_id')->count('user_id');
        $storeVisits = $query->where('visit_type', 'store')->count();
        $productVisits = $query->where('visit_type', 'product')->count();

        return [
            'total_visits' => $totalVisits,
            'unique_visitors' => $uniqueVisitors,
            'store_profile_visits' => $storeVisits,
            'product_visits' => $productVisits,
        ];
    }

    /**
     * Format visitor data for response
     */
    private function formatVisitorData($visitor, $store)
    {
        $user = $visitor->user;
        
        // Check if chat exists
        $chatExists = Chat::where('store_id', $store->id)
            ->where('user_id', $visitor->user_id)
            ->exists();

        // Handle last_visit - it might be a string from MAX() aggregation or a Carbon instance
        $lastVisit = $visitor->last_visit ?? $visitor->created_at;
        if (is_string($lastVisit)) {
            $lastVisit = Carbon::parse($lastVisit);
        }

        return [
            'id' => $visitor->id,
            'visit_type' => $visitor->visit_type,
            'last_visit' => $lastVisit,
            'formatted_date' => $lastVisit->format('d M Y, h:i A'),
            'visitor' => [
                'id' => $visitor->user_id,
                'name' => $user?->full_name ?? 'Unknown',
                'email' => $user?->email,
                'phone' => $user?->phone,
                'profile_picture' => $user && $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                'has_chat' => $chatExists,
                'is_online' => $user ? $user->isOnline() : false,
                'last_seen_at' => $user?->last_seen_at?->toIso8601String(),
                'last_seen_formatted' => $user?->getLastSeenFormatted(),
            ],
            'product' => $visitor->product ? [
                'id' => $visitor->product->id,
                'name' => $visitor->product->name,
                'price' => $visitor->product->price,
                'discount_price' => $visitor->product->discount_price,
            ] : null,
            'visit_info' => [
                'ip_address' => $visitor->ip_address,
                'user_agent' => $visitor->user_agent,
            ],
        ];
    }

    /**
     * Get detailed visitor activity
     */
    public function getVisitorActivity(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            
            // Get seller's store
            $store = $user->store;
            //if cannot find store id it can bbe the other user not the owner so lets check in the store user table
            if (!$store) {
                $storeUser = StoreUser::where('user_id', $user->id)->first();
                if ($storeUser) {
                    $store = $storeUser->store;
                }
            }
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Get all visits by this user
            $visits = StoreVisitor::where('store_id', $store->id)
                ->where('user_id', $userId)
                ->with(['product:id,name,price,discount_price'])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($visits->isEmpty()) {
                return ResponseHelper::error('No visits found for this user', 404);
            }

            // Format activities
            $activities = $visits->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'visit_type' => $visit->visit_type,
                    'visited_at' => $visit->created_at->format('d M Y, h:i A'),
                    'product' => $visit->product ? [
                        'id' => $visit->product->id,
                        'name' => $visit->product->name,
                        'price' => $visit->product->price,
                    ] : null,
                ];
            });

            // Get visitor info
            $firstVisit = $visits->first();
            $user = $firstVisit->user;
            $visitorInfo = [
                'id' => $userId,
                'name' => $user?->full_name ?? 'Unknown',
                'email' => $user?->email,
                'phone' => $user?->phone,
                'profile_picture' => $user && $user->profile_picture 
                    ? asset('storage/' . $user->profile_picture) 
                    : null,
                'first_visit' => $visits->last()->created_at->format('d M Y, h:i A'),
                'last_visit' => $visits->first()->created_at->format('d M Y, h:i A'),
                'total_visits' => $visits->count(),
                'is_online' => $user ? $user->isOnline() : false,
                'last_seen_at' => $user?->last_seen_at?->toIso8601String(),
                'last_seen_formatted' => $user?->getLastSeenFormatted(),
            ];

            return ResponseHelper::success([
                'visitor' => $visitorInfo,
                'activities' => $activities,
            ]);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Initiate chat with a visitor
     */
    public function startChatWithVisitor(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            
            // Get seller's store
            $store = $user->store;
            //if cannot find store id it can bbe the other user not the owner so lets check in the store user table
            if (!$store) {
                $storeUser = StoreUser::where('user_id', $user->id)->first();
                if ($storeUser) {
                    $store = $storeUser->store;
                }
            }
            if (!$store) {
                return ResponseHelper::error('Store not found', 404);
            }

            // Verify the user has visited the store
            $hasVisited = StoreVisitor::where('store_id', $store->id)
                ->where('user_id', $userId)
                ->exists();

            if (!$hasVisited) {
                return ResponseHelper::error('This user has not visited your store', 400);
            }

            // Check if chat already exists
            $existingChat = Chat::where('store_id', $store->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingChat) {
                return ResponseHelper::success([
                    'message' => 'Chat already exists',
                    'chat' => [
                        'id' => $existingChat->id,
                        'store_id' => $existingChat->store_id,
                        'user_id' => $existingChat->user_id,
                        'created_at' => $existingChat->created_at->format('d M Y, h:i A'),
                    ],
                ]);
            }

            // Create new chat
            $chat = Chat::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'type' => 'general',
            ]);

            // Send welcome message (optional)
            $welcomeMessage = $request->input('message', 'Hi! How can I help you?');
            if ($welcomeMessage) {
                \App\Models\ChatMessage::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $user->id,
                    'sender_type' => 'store',
                    'message' => $welcomeMessage,
                    
                ]);
            }

            return ResponseHelper::success([
                'message' => 'Chat created successfully',
                'chat' => [
                    'id' => $chat->id,
                    'store_id' => $chat->store_id,
                    'user_id' => $chat->user_id,
                    'created_at' => $chat->created_at->format('d M Y, h:i A'),
                ],
            ]);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

