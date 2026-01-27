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
                // Get escrow record for this specific store order (new flow)
                $escrowRecord = Escrow::where('store_order_id', $storeOrder->id)
                    ->where('status', 'locked')
                    ->first();

                // Fallback: Check old flow (by order_id) if no store_order_id escrow exists
                if (!$escrowRecord) {
                    $escrowRecord = Escrow::where('order_id', $storeOrder->order_id)
                        ->where('status', 'locked')
                        ->first();
                }

                if (!$escrowRecord) {
                    // No escrow funds to unlock
                    Log::info('No locked escrow found for store order', [
                        'store_order_id' => $storeOrder->id,
                        'order_id'       => $storeOrder->order_id,
                        'performed_by_admin_id' => $performedByAdminId,
                        'reason'         => $reason,
                    ]);
                    return false;
                }

                $totalAmount = $escrowRecord->amount;

                // Get store owner
                $store = Store::with('user')->find($storeOrder->store_id);
                if (!$store || !$store->user) {
                    throw new Exception('Store or store owner not found');
                }

                // Update escrow status to 'released'
                $escrowRecord->update(['status' => 'released']);

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
                    'amount'               => $totalAmount,
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

