<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\UserNotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeChat;
use App\Models\DisputeChatMessage;
use App\Models\Chat;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\Store;
use App\Models\Escrow;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Services\EscrowService;
use App\Traits\PeriodFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminDisputeController extends Controller
{
    use PeriodFilterTrait;

    protected $escrowService;

    public function __construct(EscrowService $escrowService)
    {
        $this->escrowService = $escrowService;
    }
    /**
     * Get all disputes with filtering and pagination
     */
    public function getAllDisputes(Request $request)
    {
        try {
            $query = Dispute::with([
                'disputeChat' => function ($q) {
                    $q->with(['store', 'buyer', 'seller']);
                },
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

            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            // Apply period filter (priority over date_from/date_to for backward compatibility)
            if ($period) {
                $this->applyPeriodFilter($query, $period);
            } elseif ($request->has('date_from') && $request->has('date_to')) {
                // Legacy support for date_from/date_to
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
                      ->orWhereHas('disputeChat.store', function ($storeQuery) use ($search) {
                          $storeQuery->where('store_name', 'like', "%{$search}%");
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
                'disputeChat' => function ($q) {
                    $q->with([
                        'store',
                        'buyer',
                        'seller',
                        'messages' => function ($msgQuery) {
                            $msgQuery->with('sender')->orderBy('created_at', 'desc');
                        }
                    ]);
                },
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

            // Notify buyer and seller about status update
            $dispute->load('user', 'storeOrder.store.user');
            $buyer = $dispute->user;
            $seller = $dispute->storeOrder->store->user ?? null;

            $statusMessages = [
                'pending' => 'Your dispute is pending review',
                'on_hold' => 'Your dispute is on hold',
                'resolved' => 'Your dispute has been resolved',
                'closed' => 'Your dispute has been closed'
            ];

            $statusMessage = $statusMessages[$request->status] ?? 'Your dispute status has been updated';
            $notesText = $request->resolution_notes ? " Notes: {$request->resolution_notes}" : '';

            if ($buyer) {
                \App\Helpers\UserNotificationHelper::notify(
                    $buyer->id,
                    'Dispute Status Updated',
                    "{$statusMessage}.{$notesText}",
                    [
                        'type' => 'dispute_status_update',
                        'dispute_id' => $dispute->id,
                        'status' => $request->status,
                        'won_by' => $request->won_by ?? null
                    ]
                );
            }

            if ($seller) {
                \App\Helpers\UserNotificationHelper::notify(
                    $seller->id,
                    'Dispute Status Updated',
                    "{$statusMessage}.{$notesText}",
                    [
                        'type' => 'dispute_status_update',
                        'dispute_id' => $dispute->id,
                        'status' => $request->status,
                        'won_by' => $request->won_by ?? null
                    ]
                );
            }

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
     * Resolve dispute with escrow release handling
     */
    public function resolveDispute(Request $request, $disputeId)
    {
        try {
            $request->validate([
                'resolution_notes' => 'required|string|max:1000',
                'won_by' => 'required|in:buyer,seller,admin',
            ]);

            $dispute = Dispute::with(['storeOrder.order', 'storeOrder.store.user', 'user'])->findOrFail($disputeId);
            
            if (!$dispute->storeOrder) {
                return ResponseHelper::error('Store order not found for this dispute.', 404);
            }

            $storeOrder = $dispute->storeOrder;
            $order = $storeOrder->order;
            
            // Check if order payment status is after payment
            $isPaid = $order && $order->payment_status === 'paid';
            
            $escrowReleased = false;
            $escrowAmount = 0;
            
            // Handle escrow release based on winner if order is paid
            if ($isPaid) {
                // Get escrow record
                $escrowRecord = Escrow::where('store_order_id', $storeOrder->id)
                    ->where('status', 'locked')
                    ->first();

                // Fallback: Check old flow (by order_id) if no store_order_id escrow exists
                if (!$escrowRecord) {
                    $escrowRecord = Escrow::where('order_id', $storeOrder->order_id)
                        ->where('status', 'locked')
                        ->first();
                }

                if ($escrowRecord) {
                    $escrowAmount = $escrowRecord->amount;
                    
                    if ($request->won_by === 'seller') {
                        // Release escrow to seller
                        $escrowReleased = $this->escrowService->releaseForStoreOrder(
                            $storeOrder,
                            $request->user()->id,
                            "Dispute resolved in favor of seller - Dispute #{$disputeId}"
                        );
                    } elseif ($request->won_by === 'buyer') {
                        // Refund escrow to buyer
                        $escrowReleased = $this->refundEscrowToBuyer(
                            $escrowRecord,
                            $storeOrder,
                            $dispute->user,
                            $request->user()->id,
                            "Dispute resolved in favor of buyer - Dispute #{$disputeId}"
                        );
                    }
                    // If won_by is 'admin', escrow can be handled separately or left as is
                }
            }

            // Update dispute status
            $dispute->status = 'resolved';
            $dispute->resolution_notes = $request->resolution_notes;
            $dispute->won_by = $request->won_by;
            $dispute->resolved_at = now();
            $dispute->save();

            // Get buyer and seller for notifications
            $buyer = $dispute->user;
            $seller = $storeOrder->store->user ?? null;

            // Send notifications
            $winnerName = $request->won_by === 'buyer' ? 'You (Buyer)' : ($request->won_by === 'seller' ? 'Seller' : 'Admin');
            $resolutionMessage = "Dispute #{$disputeId} has been resolved in favor of {$winnerName}.";
            
            if ($isPaid && $escrowReleased) {
                $resolutionMessage .= " Escrow amount of N" . number_format($escrowAmount, 2) . " has been " . 
                    ($request->won_by === 'buyer' ? 'refunded to your wallet' : 'released to seller') . ".";
            }

            if ($buyer) {
                UserNotificationHelper::notify(
                    $buyer->id,
                    'Dispute Resolved',
                    $resolutionMessage . ($request->resolution_notes ? "\n\nResolution Notes: {$request->resolution_notes}" : ''),
                    [
                        'type' => 'dispute_resolved',
                        'dispute_id' => $dispute->id,
                        'won_by' => $request->won_by,
                        'escrow_released' => $escrowReleased,
                        'escrow_amount' => $escrowAmount
                    ]
                );
            }

            if ($seller) {
                UserNotificationHelper::notify(
                    $seller->id,
                    'Dispute Resolved',
                    $resolutionMessage . ($request->resolution_notes ? "\n\nResolution Notes: {$request->resolution_notes}" : ''),
                    [
                        'type' => 'dispute_resolved',
                        'dispute_id' => $dispute->id,
                        'won_by' => $request->won_by,
                        'escrow_released' => $escrowReleased,
                        'escrow_amount' => $escrowAmount
                    ]
                );
            }

            Log::info("Dispute #{$disputeId} resolved in favor of {$request->won_by}", [
                'escrow_released' => $escrowReleased,
                'escrow_amount' => $escrowAmount,
                'is_paid' => $isPaid
            ]);

            return ResponseHelper::success([
                'dispute' => $this->formatDisputeData($dispute),
                'escrow_released' => $escrowReleased,
                'escrow_amount' => $escrowAmount
            ], 'Dispute resolved successfully.' . ($escrowReleased ? ' Escrow has been processed.' : ''));

        } catch (\Exception $e) {
            Log::error("Error resolving dispute: " . $e->getMessage(), [
                'dispute_id' => $disputeId,
                'trace' => $e->getTraceAsString()
            ]);
            return ResponseHelper::error('Failed to resolve dispute: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Refund escrow amount to buyer's wallet
     */
    private function refundEscrowToBuyer(Escrow $escrowRecord, StoreOrder $storeOrder, User $buyer, ?int $adminId = null, ?string $reason = null): bool
    {
        try {
            return DB::transaction(function () use ($escrowRecord, $storeOrder, $buyer, $adminId, $reason) {
                $refundAmount = $escrowRecord->amount;

                // Update escrow status to 'refunded'
                $escrowRecord->update(['status' => 'refunded']);

                // Create or update buyer's wallet
                $buyerWallet = Wallet::firstOrCreate(
                    ['user_id' => $buyer->id],
                    [
                        'shopping_balance' => 0,
                        'reward_balance' => 0,
                        'referral_balance' => 0,
                        'loyality_points' => 0,
                    ]
                );

                // Add funds to buyer's shopping balance
                $buyerWallet->increment('shopping_balance', $refundAmount);

                // Create transaction record for buyer
                $txId = 'REFUND-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                Transaction::create([
                    'tx_id' => $txId,
                    'amount' => $refundAmount,
                    'status' => 'successful',
                    'type' => 'dispute_refund',
                    'order_id' => $storeOrder->order_id,
                    'user_id' => $buyer->id,
                ]);

                Log::info('Escrow refunded to buyer', [
                    'store_order_id' => $storeOrder->id,
                    'order_id' => $storeOrder->order_id,
                    'buyer_id' => $buyer->id,
                    'amount' => $refundAmount,
                    'performed_by_admin_id' => $adminId,
                    'reason' => $reason,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to refund escrow to buyer', [
                'error' => $e->getMessage(),
                'store_order_id' => $storeOrder->id ?? null,
                'order_id' => $storeOrder->order_id ?? null,
                'buyer_id' => $buyer->id ?? null,
                'performed_by_admin_id' => $adminId,
                'reason' => $reason,
            ]);

            return false;
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

        // Add dispute chat information (new system)
        if ($dispute->disputeChat) {
            $data['dispute_chat'] = [
                'id' => $dispute->disputeChat->id,
                'buyer' => $dispute->disputeChat->buyer ? [
                    'id' => $dispute->disputeChat->buyer->id,
                    'name' => $dispute->disputeChat->buyer->full_name ?? $dispute->disputeChat->buyer->first_name . ' ' . $dispute->disputeChat->buyer->last_name,
                    'email' => $dispute->disputeChat->buyer->email,
                ] : null,
                'seller' => $dispute->disputeChat->seller ? [
                    'id' => $dispute->disputeChat->seller->id,
                    'name' => $dispute->disputeChat->seller->full_name ?? $dispute->disputeChat->seller->first_name . ' ' . $dispute->disputeChat->seller->last_name,
                    'email' => $dispute->disputeChat->seller->email,
                ] : null,
                'store' => $dispute->disputeChat->store ? [
                    'id' => $dispute->disputeChat->store->id,
                    'name' => $dispute->disputeChat->store->store_name,
                ] : null,
            ];

            if ($detailed && $dispute->disputeChat->messages) {
                $data['dispute_chat']['messages'] = $dispute->disputeChat->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'sender_id' => $message->sender_id,
                        'sender_type' => $message->sender_type,
                        'sender_name' => $message->sender ? ($message->sender->full_name ?? $message->sender->first_name . ' ' . $message->sender->last_name) : 'Unknown',
                        'message' => $message->message,
                        'image' => $message->image ? asset('storage/' . $message->image) : null,
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at,
                    ];
                });
            }
        }

        return $data;
    }

    /**
     * Send message in dispute chat (Admin)
     */
    public function sendMessage(Request $request, $disputeId)
    {
        try {
            $request->validate([
                'message' => 'nullable|string|max:5000',
                'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $admin = $request->user();

            $dispute = Dispute::with('disputeChat')
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
                'sender_id' => $admin->id,
                'sender_type' => 'admin',
                'message' => $request->message,
                'image' => $imagePath,
                'is_read' => false,
            ]);

            // Mark all messages as read when admin sends a message
            $dispute->disputeChat->messages()
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return ResponseHelper::success([
                'message' => $message->load('sender')
            ], 'Message sent successfully.');

        } catch (\Exception $e) {
            Log::error("Error sending admin message: " . $e->getMessage());
            return ResponseHelper::error('Failed to send message.', 500);
        }
    }

    /**
     * Get dispute chat messages (Admin)
     */
    public function getDisputeChatMessages($disputeId)
    {
        try {
            $dispute = Dispute::with([
                'disputeChat.messages.sender',
                'disputeChat.buyer',
                'disputeChat.seller',
                'disputeChat.store'
            ])
                ->findOrFail($disputeId);

            if (!$dispute->disputeChat) {
                return ResponseHelper::error('Dispute chat not found.', 404);
            }

            return ResponseHelper::success([
                'dispute_chat' => [
                    'id' => $dispute->disputeChat->id,
                    'buyer' => $dispute->disputeChat->buyer ? [
                        'id' => $dispute->disputeChat->buyer->id,
                        'name' => $dispute->disputeChat->buyer->full_name ?? $dispute->disputeChat->buyer->first_name . ' ' . $dispute->disputeChat->buyer->last_name,
                        'email' => $dispute->disputeChat->buyer->email,
                    ] : null,
                    'seller' => $dispute->disputeChat->seller ? [
                        'id' => $dispute->disputeChat->seller->id,
                        'name' => $dispute->disputeChat->seller->full_name ?? $dispute->disputeChat->seller->first_name . ' ' . $dispute->disputeChat->seller->last_name,
                        'email' => $dispute->disputeChat->seller->email,
                    ] : null,
                    'store' => $dispute->disputeChat->store ? [
                        'id' => $dispute->disputeChat->store->id,
                        'name' => $dispute->disputeChat->store->store_name,
                    ] : null,
                    'messages' => $dispute->disputeChat->messages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'sender_id' => $message->sender_id,
                            'sender_type' => $message->sender_type,
                            'sender_name' => $message->sender ? ($message->sender->full_name ?? $message->sender->first_name . ' ' . $message->sender->last_name) : 'Unknown',
                            'message' => $message->message,
                            'image' => $message->image ? asset('storage/' . $message->image) : null,
                            'is_read' => $message->is_read,
                            'created_at' => $message->created_at,
                        ];
                    }),
                ]
            ], 'Dispute chat messages retrieved successfully.');

        } catch (\Exception $e) {
            Log::error("Error fetching dispute chat messages: " . $e->getMessage());
            return ResponseHelper::error('Failed to fetch messages.', 500);
        }
    }
}
