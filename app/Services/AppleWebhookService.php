<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppleWebhookService
{
    /**
     * Handle Apple server-to-server notification
     * 
     * @param array $notificationPayload Decoded JWT payload from Apple
     * @return bool Success status
     */
    public function handleNotification(array $notificationPayload): bool
    {
        try {
            $notificationType = $notificationPayload['notification_type'] ?? null;
            $signedPayload = $notificationPayload['signedPayload'] ?? null;

            if (!$notificationType || !$signedPayload) {
                Log::warning('Invalid Apple webhook notification', ['payload' => $notificationPayload]);
                return false;
            }

            // Decode the signed payload (JWT)
            $decodedPayload = $this->decodeSignedPayload($signedPayload);
            
            if (!$decodedPayload) {
                Log::error('Failed to decode Apple signed payload');
                return false;
            }

            $data = $decodedPayload['data'] ?? [];
            $transactionInfo = $data['signedTransactionInfo'] ?? null;

            if (!$transactionInfo) {
                Log::warning('No transaction info in Apple notification');
                return false;
            }

            // Decode transaction info
            $transactionData = $this->decodeSignedPayload($transactionInfo);
            
            if (!$transactionData) {
                Log::error('Failed to decode Apple transaction info');
                return false;
            }

            $originalTransactionId = $transactionData['originalTransactionId'] ?? null;
            
            if (!$originalTransactionId) {
                Log::warning('No original transaction ID in Apple notification');
                return false;
            }

            // Handle different notification types
            return match($notificationType) {
                'INITIAL_BUY' => $this->handleInitialBuy($transactionData),
                'DID_RENEW' => $this->handleRenewal($transactionData),
                'DID_FAIL_TO_RENEW' => $this->handleFailedRenewal($transactionData),
                'DID_CANCEL' => $this->handleCancellation($transactionData),
                'EXPIRED' => $this->handleExpiration($transactionData),
                'GRACE_PERIOD_EXPIRED' => $this->handleGracePeriodExpired($transactionData),
                'REFUND' => $this->handleRefund($transactionData),
                default => $this->handleUnknownNotification($notificationType, $transactionData),
            };
        } catch (Exception $e) {
            Log::error('Error handling Apple webhook notification', [
                'error' => $e->getMessage(),
                'payload' => $notificationPayload
            ]);
            return false;
        }
    }

    /**
     * Handle initial purchase
     */
    private function handleInitialBuy(array $transactionData): bool
    {
        // Initial purchase is usually handled by validate-receipt endpoint
        // But we can log it here for tracking
        Log::info('Apple INITIAL_BUY notification', ['transaction' => $transactionData]);
        return true;
    }

    /**
     * Handle subscription renewal - MOST IMPORTANT
     */
    private function handleRenewal(array $transactionData): bool
    {
        try {
            $originalTransactionId = $transactionData['originalTransactionId'] ?? null;
            $transactionId = $transactionData['transactionId'] ?? null;
            $expiresDateMs = $transactionData['expiresDate'] ?? null;

            if (!$originalTransactionId || !$transactionId) {
                Log::error('Missing transaction IDs in renewal notification');
                return false;
            }

            // Find subscription by original transaction ID
            $subscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)
                ->where('status', 'active')
                ->first();

            if (!$subscription) {
                Log::warning('Subscription not found for renewal', [
                    'original_transaction_id' => $originalTransactionId
                ]);
                return false;
            }

            // Check if this transaction ID was already processed
            if (Subscription::where('apple_transaction_id', $transactionId)->exists()) {
                Log::info('Renewal transaction already processed', ['transaction_id' => $transactionId]);
                return true; // Already processed, return success
            }

            DB::beginTransaction();

            // Calculate new end date
            $plan = $subscription->plan;
            $currentEndDate = Carbon::parse($subscription->end_date);
            $newEndDate = $currentEndDate->copy()->addDays($plan->duration_days);

            // If Apple provides expiry date, use it
            if ($expiresDateMs) {
                $newEndDate = Carbon::createFromTimestamp((int)($expiresDateMs / 1000));
            }

            // Update subscription
            $subscription->update([
                'end_date' => $newEndDate,
                'status' => 'active',
                'apple_transaction_id' => $transactionId,
                'is_auto_renewable' => true,
            ]);

            // Update user plan if needed
            if ($subscription->store && $subscription->store->user) {
                $user = $subscription->store->user;
                $user->plan = $plan->base_name;
                $user->save();
            }

            DB::commit();

            Log::info('Subscription renewed successfully', [
                'subscription_id' => $subscription->id,
                'new_end_date' => $newEndDate,
                'transaction_id' => $transactionId
            ]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error handling renewal', [
                'error' => $e->getMessage(),
                'transaction' => $transactionData
            ]);
            return false;
        }
    }

    /**
     * Handle failed renewal
     */
    private function handleFailedRenewal(array $transactionData): bool
    {
        $originalTransactionId = $transactionData['originalTransactionId'] ?? null;
        
        if ($originalTransactionId) {
            $subscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)->first();
            if ($subscription) {
                // Subscription is still active until expiry, but won't auto-renew
                Log::info('Renewal failed for subscription', ['subscription_id' => $subscription->id]);
            }
        }
        
        return true;
    }

    /**
     * Handle cancellation
     */
    private function handleCancellation(array $transactionData): bool
    {
        $originalTransactionId = $transactionData['originalTransactionId'] ?? null;
        
        if ($originalTransactionId) {
            $subscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)->first();
            if ($subscription && $subscription->status === 'active') {
                // Don't cancel immediately - let it expire naturally
                // Just mark that it won't auto-renew
                $subscription->update(['is_auto_renewable' => false]);
                Log::info('Subscription cancelled (will expire at end date)', [
                    'subscription_id' => $subscription->id
                ]);
            }
        }
        
        return true;
    }

    /**
     * Handle expiration
     */
    private function handleExpiration(array $transactionData): bool
    {
        $originalTransactionId = $transactionData['originalTransactionId'] ?? null;
        
        if ($originalTransactionId) {
            $subscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)->first();
            if ($subscription) {
                $subscription->update(['status' => 'expired']);
                Log::info('Subscription expired', ['subscription_id' => $subscription->id]);
            }
        }
        
        return true;
    }

    /**
     * Handle grace period expiration
     */
    private function handleGracePeriodExpired(array $transactionData): bool
    {
        return $this->handleExpiration($transactionData);
    }

    /**
     * Handle refund
     */
    private function handleRefund(array $transactionData): bool
    {
        $originalTransactionId = $transactionData['originalTransactionId'] ?? null;
        
        if ($originalTransactionId) {
            $subscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)->first();
            if ($subscription) {
                $subscription->update([
                    'status' => 'cancelled',
                    'is_auto_renewable' => false
                ]);
                Log::info('Subscription refunded', ['subscription_id' => $subscription->id]);
            }
        }
        
        return true;
    }

    /**
     * Handle unknown notification type
     */
    private function handleUnknownNotification(string $type, array $transactionData): bool
    {
        Log::warning('Unknown Apple notification type', [
            'type' => $type,
            'transaction' => $transactionData
        ]);
        return true; // Always return true to acknowledge receipt
    }

    /**
     * Decode JWT signed payload from Apple
     * Note: This is a simplified version. In production, you should verify the JWT signature
     * using Apple's public keys. For now, we'll decode without verification.
     * 
     * @param string $signedPayload JWT token
     * @return array|null Decoded payload or null on failure
     */
    private function decodeSignedPayload(string $signedPayload): ?array
    {
        try {
            // JWT has 3 parts separated by dots: header.payload.signature
            $parts = explode('.', $signedPayload);
            
            if (count($parts) !== 3) {
                return null;
            }

            // Decode payload (second part)
            $payload = $parts[1];
            
            // Add padding if needed
            $padding = 4 - (strlen($payload) % 4);
            if ($padding !== 4) {
                $payload .= str_repeat('=', $padding);
            }

            $decoded = base64_decode($payload, true);
            
            if ($decoded === false) {
                return null;
            }

            return json_decode($decoded, true);
        } catch (Exception $e) {
            Log::error('Error decoding Apple signed payload', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

