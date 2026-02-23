<?php

namespace App\Services;

use App\Models\Escrow;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\Transaction;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscrowService
{
    /**
     * Release escrow funds for a given store order and credit the seller wallet.
     *
     * This is shared between seller flow (delivery code verification)
     * and admin flow (manual/override release).
     *
     * @param  StoreOrder  $storeOrder
     * @param  int|null    $performedByAdminId  Optional admin user ID for audit logging
     * @param  string|null $reason              Optional reason/context for the release
     * @return bool         True if funds were released, false if no locked escrow was found or an error occurred
     */
    public function releaseForStoreOrder(StoreOrder $storeOrder, ?int $performedByAdminId = null, ?string $reason = null): bool
    {
        try {
            return DB::transaction(function () use ($storeOrder, $performedByAdminId, $reason) {
                // Get ALL escrow records for this specific store order
                // Escrows can be created:
                // 1. At store_order level (order_item_id = NULL) - single escrow per store_order
                // 2. Per order_item (order_item_id set) - multiple escrows per store_order
                $escrowRecords = Escrow::where('store_order_id', $storeOrder->id)
                    ->where('status', 'locked')
                    ->get();

                // Fallback: Check old flow (by order_id and order_item) if no store_order_id escrows exist
                if ($escrowRecords->isEmpty()) {
                    // Load items if not already loaded
                    if (!$storeOrder->relationLoaded('items')) {
                        $storeOrder->load('items');
                    }
                    
                    // Get all order items for this store order
                    $orderItemIds = $storeOrder->items->pluck('id')->toArray();
                    if (!empty($orderItemIds)) {
                        $escrowRecords = Escrow::where('order_id', $storeOrder->order_id)
                            ->whereIn('order_item_id', $orderItemIds)
                        ->where('status', 'locked')
                            ->get();
                    }
                }

                if ($escrowRecords->isEmpty()) {
                    // No escrow funds to unlock
                    Log::info('No locked escrow found for store order', [
                        'store_order_id' => $storeOrder->id,
                        'order_id'       => $storeOrder->order_id,
                        'performed_by_admin_id' => $performedByAdminId,
                        'reason'         => $reason,
                    ]);
                    return false;
                }

                // Sum up all escrow amounts for this store order
                $totalAmount = $escrowRecords->sum('amount');

                // Get store owner
                $store = Store::with('user')->find($storeOrder->store_id);
                if (!$store || !$store->user) {
                    throw new Exception('Store or store owner not found');
                }

                // Update ALL escrow records status to 'released'
                Escrow::whereIn('id', $escrowRecords->pluck('id')->toArray())
                    ->update(['status' => 'released']);

                // Create or update seller's wallet
                $sellerWallet = Wallet::firstOrCreate(
                    ['user_id' => $store->user->id],
                    [
                        'shopping_balance' => 0,
                        'reward_balance'   => 0,
                        'referral_balance' => 0,
                        'loyality_points'  => 0,
                    ]
                );

                // Add funds to seller's shopping balance
                $sellerWallet->increment('shopping_balance', $totalAmount);

                // Create transaction record for seller
                $txId = 'SELLER-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                Transaction::create([
                    'tx_id'   => $txId,
                    'amount'  => $totalAmount,
                    'status'  => 'successful',
                    'type'    => 'escrow_release',
                    'order_id'=> $storeOrder->order_id,
                    'user_id' => $store->user->id,
                    // Optional: could add a meta/description field if your schema supports it
                ]);

                Log::info('Escrow funds released', [
                    'store_order_id'       => $storeOrder->id,
                    'order_id'             => $storeOrder->order_id,
                    'escrow_count'         => $escrowRecords->count(),
                    'total_amount'         => $totalAmount,
                    'performed_by_admin_id'=> $performedByAdminId,
                    'reason'               => $reason,
                ]);

                return true;
            });
        } catch (Exception $e) {
            // Log the error but don't hard-fail the caller
            Log::error('Failed to release escrow funds', [
                'error'          => $e->getMessage(),
                'store_order_id' => $storeOrder->id ?? null,
                'order_id'       => $storeOrder->order_id ?? null,
                'performed_by_admin_id' => $performedByAdminId,
                'reason'         => $reason,
            ]);

            return false;
        }
    }
}

