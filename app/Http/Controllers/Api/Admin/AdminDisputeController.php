<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Chat;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDisputeController extends Controller
{
    /**
     * Get all disputes with filtering and pagination
     */
    public function getAllDisputes(Request $request)
    {
        try {
            $query = Dispute::with([
                'chat' => function ($q) {
                    $q->with(['store', 'user']);
                },
                'storeOrder' => function ($q) {
                    $q->with(['order', 'store']);
                },
                'user'
            ]);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('details', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      })
                      ->orWhereHas('chat.store', function ($storeQuery) use ($search) {
                          $storeQuery->where('store_name', 'like', "%{$search}%");
                      });
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->get('per_page', 20);
            $disputes = $query->paginate($perPage);

            // Format the response
            $formattedDisputes = $disputes->map(function ($dispute) {
                return $this->formatDisputeData($dispute);
            });

            return ResponseHelper::success([
                'disputes' => $formattedDisputes,
                'pagination' => [
                    'current_page' => $disputes->currentPage(),
                    'last_page' => $disputes->lastPage(),
                    'per_page' => $disputes->perPage(),
                    'total' => $disputes->total(),
                ],
                'filters' => [
                    'status' => $request->status,
                    'category' => $request->category,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'search' => $request->search,
                ]
            ], 'Disputes retrieved successfully.');

        } catch (\Exception $e) {
            Log::error("Error fetching disputes: " . $e->getMessage());
            return ResponseHelper::error('Failed to fetch disputes.', 500);
        }
    }

    /**
     * Get dispute statistics
     */
    public function getDisputeStatistics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            $stats = [
                'total_disputes' => Dispute::count(),
                'pending_disputes' => Dispute::where('status', 'pending')->count(),
                'resolved_disputes' => Dispute::where('status', 'resolved')->count(),
                'on_hold_disputes' => Dispute::where('status', 'on_hold')->count(),
                'recent_disputes' => Dispute::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                'disputes_by_category' => Dispute::select('category', DB::raw('count(*) as count'))
                    ->groupBy('category')
                    ->get()
                    ->pluck('count', 'category'),
                'disputes_by_status' => Dispute::select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status'),
            ];

            return ResponseHelper::success($stats, 'Dispute statistics retrieved successfully.');

        } catch (\Exception $e) {
            Log::error("Error fetching dispute statistics: " . $e->getMessage());
            return ResponseHelper::error('Failed to fetch dispute statistics.', 500);
        }
    }

    /**
     * Get detailed dispute information
     */
    public function getDisputeDetails($disputeId)
    {
        try {
            $dispute = Dispute::with([
                'chat' => function ($q) {
                    $q->with(['store', 'user', 'messages' => function ($msgQuery) {
                        $msgQuery->orderBy('created_at', 'desc')->limit(10);
                    }]);
                },
                'storeOrder' => function ($q) {
                    $q->with(['order', 'store', 'items']);
                },
                'user'
            ])->findOrFail($disputeId);

            return ResponseHelper::success([
                'dispute' => $this->formatDisputeData($dispute, true)
            ], 'Dispute details retrieved successfully.');

        } catch (\Exception $e) {
            Log::error("Error fetching dispute details: " . $e->getMessage());
            return ResponseHelper::error('Dispute not found.', 404);
        }
    }

    /**
     * Update dispute status
     */
    public function updateDisputeStatus(Request $request, $disputeId)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,on_hold,resolved,closed',
                'resolution_notes' => 'nullable|string|max:1000',
                'won_by' => 'nullable|in:buyer,seller,admin',
            ]);

            $dispute = Dispute::findOrFail($disputeId);
            
            $oldStatus = $dispute->status;
            $dispute->status = $request->status;
            
            if ($request->has('resolution_notes')) {
                $dispute->resolution_notes = $request->resolution_notes;
            }
            
            if ($request->has('won_by')) {
                $dispute->won_by = $request->won_by;
            }

            $dispute->save();

            // Log the status change
            Log::info("Dispute #{$disputeId} status changed from {$oldStatus} to {$dispute->status}");

            return ResponseHelper::success([
                'dispute' => $this->formatDisputeData($dispute)
            ], 'Dispute status updated successfully.');

        } catch (\Exception $e) {
            Log::error("Error updating dispute status: " . $e->getMessage());
            return ResponseHelper::error('Failed to update dispute status.', 500);
        }
    }

    /**
     * Resolve dispute
     */
    public function resolveDispute(Request $request, $disputeId)
    {
        try {
            $request->validate([
                'resolution_notes' => 'required|string|max:1000',
                'won_by' => 'required|in:buyer,seller,admin',
            ]);

            $dispute = Dispute::findOrFail($disputeId);
            $dispute->status = 'resolved';
            $dispute->resolution_notes = $request->resolution_notes;
            $dispute->won_by = $request->won_by;
            $dispute->resolved_at = now();
            $dispute->save();

            Log::info("Dispute #{$disputeId} resolved in favor of {$request->won_by}");

            return ResponseHelper::success([
                'dispute' => $this->formatDisputeData($dispute)
            ], 'Dispute resolved successfully.');

        } catch (\Exception $e) {
            Log::error("Error resolving dispute: " . $e->getMessage());
            return ResponseHelper::error('Failed to resolve dispute.', 500);
        }
    }

    /**
     * Close dispute
     */
    public function closeDispute(Request $request, $disputeId)
    {
        try {
            $request->validate([
                'resolution_notes' => 'nullable|string|max:1000',
            ]);

            $dispute = Dispute::findOrFail($disputeId);
            $dispute->status = 'closed';
            
            if ($request->has('resolution_notes')) {
                $dispute->resolution_notes = $request->resolution_notes;
            }
            
            $dispute->closed_at = now();
            $dispute->save();

            Log::info("Dispute #{$disputeId} closed");

            return ResponseHelper::success([
                'dispute' => $this->formatDisputeData($dispute)
            ], 'Dispute closed successfully.');

        } catch (\Exception $e) {
            Log::error("Error closing dispute: " . $e->getMessage());
            return ResponseHelper::error('Failed to close dispute.', 500);
        }
    }

    /**
     * Bulk actions on disputes
     */
    public function bulkAction(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|in:update_status,resolve,close',
                'dispute_ids' => 'required|array|min:1',
                'dispute_ids.*' => 'integer|exists:disputes,id',
                'status' => 'required_if:action,update_status|in:pending,on_hold,resolved,closed',
                'won_by' => 'required_if:action,resolve|in:buyer,seller,admin',
                'resolution_notes' => 'nullable|string|max:1000',
            ]);

            $disputeIds = $request->dispute_ids;
            $action = $request->action;
            $updatedCount = 0;

            foreach ($disputeIds as $disputeId) {
                $dispute = Dispute::find($disputeId);
                if (!$dispute) continue;

                switch ($action) {
                    case 'update_status':
                        $dispute->status = $request->status;
                        break;
                    case 'resolve':
                        $dispute->status = 'resolved';
                        $dispute->won_by = $request->won_by;
                        $dispute->resolved_at = now();
                        break;
                    case 'close':
                        $dispute->status = 'closed';
                        $dispute->closed_at = now();
                        break;
                }

                if ($request->has('resolution_notes')) {
                    $dispute->resolution_notes = $request->resolution_notes;
                }

                $dispute->save();
                $updatedCount++;
            }

            Log::info("Bulk action '{$action}' performed on {$updatedCount} disputes");

            return ResponseHelper::success([
                'updated_count' => $updatedCount,
                'action' => $action
            ], "Bulk action completed successfully. {$updatedCount} disputes updated.");

        } catch (\Exception $e) {
            Log::error("Error in bulk action: " . $e->getMessage());
            return ResponseHelper::error('Failed to perform bulk action.', 500);
        }
    }

    /**
     * Get dispute analytics
     */
    public function getDisputeAnalytics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            // Dispute trends over time
            $trends = Dispute::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Resolution time analytics
            $resolutionTimes = Dispute::whereNotNull('resolved_at')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('
                    AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_resolution_hours,
                    MIN(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as min_resolution_hours,
                    MAX(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as max_resolution_hours
                ')
                ->first();

            // Top dispute categories
            $topCategories = Dispute::select('category', DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('category')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            // Resolution outcomes
            $outcomes = Dispute::whereNotNull('won_by')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->select('won_by', DB::raw('COUNT(*) as count'))
                ->groupBy('won_by')
                ->get();

            return ResponseHelper::success([
                'trends' => $trends,
                'resolution_times' => $resolutionTimes,
                'top_categories' => $topCategories,
                'outcomes' => $outcomes,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ], 'Dispute analytics retrieved successfully.');

        } catch (\Exception $e) {
            Log::error("Error fetching dispute analytics: " . $e->getMessage());
            return ResponseHelper::error('Failed to fetch dispute analytics.', 500);
        }
    }

    /**
     * Format dispute data for response
     */
    private function formatDisputeData($dispute, $detailed = false)
    {
        $data = [
            'id' => $dispute->id,
            'category' => $dispute->category,
            'details' => $dispute->details,
            'images' => $dispute->images,
            'status' => $dispute->status,
            'won_by' => $dispute->won_by,
            'resolution_notes' => $dispute->resolution_notes,
            'created_at' => $dispute->created_at,
            'updated_at' => $dispute->updated_at,
            'resolved_at' => $dispute->resolved_at,
            'closed_at' => $dispute->closed_at,
        ];

        // Add user information
        if ($dispute->user) {
            // Determine profile picture based on user role
            $profilePicture = null;
            if ($dispute->user->role === 'seller' && $dispute->user->store && $dispute->user->store->profile_image) {
                $profilePicture = asset('storage/' . $dispute->user->store->profile_image);
            } elseif ($dispute->user->profile_picture) {
                $profilePicture = asset('storage/' . $dispute->user->profile_picture);
            }

            $data['user'] = [
                'id' => $dispute->user->id,
                'name' => $dispute->user->first_name . ' ' . $dispute->user->last_name,
                'email' => $dispute->user->email,
                'phone' => $dispute->user->phone,
                'profile_picture' => $profilePicture,
            ];
        }

        // Add chat information
        if ($dispute->chat) {
            // Store profile picture
            $storeProfilePicture = null;
            if ($dispute->chat->store && $dispute->chat->store->profile_image) {
                $storeProfilePicture = asset('storage/' . $dispute->chat->store->profile_image);
            }

            $data['chat'] = [
                'id' => $dispute->chat->id,
                'store_name' => $dispute->chat->store->store_name ?? 'N/A',
                'store_profile_picture' => $storeProfilePicture,
                'user_name' => $dispute->chat->user->first_name . ' ' . $dispute->chat->user->last_name ?? 'N/A',
                'last_message' => $dispute->chat->messages->first()->message ?? 'No messages',
                'created_at' => $dispute->chat->created_at,
            ];

            if ($detailed && $dispute->chat->messages) {
                $data['chat']['recent_messages'] = $dispute->chat->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_type' => $message->sender_type,
                        'created_at' => $message->created_at,
                    ];
                });
            }
        }

        // Add store order information
        if ($dispute->storeOrder) {
            $data['store_order'] = [
                'id' => $dispute->storeOrder->id,
                'order_id' => $dispute->storeOrder->order_id,
                'status' => $dispute->storeOrder->status,
                'items_subtotal' => $dispute->storeOrder->items_subtotal,
                'shipping_fee' => $dispute->storeOrder->shipping_fee,
                'subtotal_with_shipping' => $dispute->storeOrder->subtotal_with_shipping,
                'created_at' => $dispute->storeOrder->created_at,
            ];

            if ($detailed && $dispute->storeOrder->items) {
                $data['store_order']['items'] = $dispute->storeOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'unit_price' => $item->unit_price,
                        'qty' => $item->qty,
                        'line_total' => $item->line_total,
                    ];
                });
            }
        }

        return $data;
    }
}
