<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppleReceiptValidationService
{
    private string $sandboxUrl = 'https://api.storekit-sandbox.itunes.apple.com/inApps/v1/validateReceipt';
    private string $productionUrl = 'https://api.storekit.itunes.apple.com/inApps/v1/validateReceipt';
    private ?string $sharedSecret;

    public function __construct()
    {
        $this->sharedSecret = config('services.apple.shared_secret');
    }

    /**
     * Validate Apple receipt with App Store Server API
     * 
     * @param string $receiptData Base64 encoded receipt data
     * @param bool $isSandbox Whether to use sandbox environment
     * @return array Validation response from Apple
     * @throws Exception
     */
    public function validateReceipt(string $receiptData, bool $isSandbox = false): array
    {
        $url = $isSandbox ? $this->sandboxUrl : $this->productionUrl;
        
        $payload = [
            'receipt-data' => $receiptData,
        ];

        // Add shared secret if available (for auto-renewable subscriptions)
        if ($this->sharedSecret) {
            $payload['password'] = $this->sharedSecret;
        }

        try {
            $response = Http::timeout(30)->post($url, $payload);
            
            if (!$response->successful()) {
                Log::error('Apple receipt validation failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception('Failed to validate receipt with Apple');
            }

            $data = $response->json();
            
            // Check status code
            if (isset($data['status']) && $data['status'] !== 0) {
                // Status 21007 means receipt is from sandbox but sent to production
                if ($data['status'] === 21007 && !$isSandbox) {
                    return $this->validateReceipt($receiptData, true);
                }
                
                throw new Exception($this->getStatusMessage($data['status']));
            }

            return $data;
        } catch (Exception $e) {
            Log::error('Apple receipt validation error', [
                'error' => $e->getMessage(),
                'is_sandbox' => $isSandbox
            ]);
            throw $e;
        }
    }

    /**
     * Extract subscription information from Apple receipt response
     * 
     * @param array $receiptData Apple receipt validation response
     * @return array Subscription details
     */
    public function extractSubscriptionInfo(array $receiptData): array
    {
        $latestReceiptInfo = $receiptData['latest_receipt_info'] ?? [];
        $pendingRenewalInfo = $receiptData['pending_renewal_info'] ?? [];

        if (empty($latestReceiptInfo)) {
            throw new Exception('No subscription information found in receipt');
        }

        // Get the latest transaction (most recent subscription)
        $latestTransaction = end($latestReceiptInfo);
        
        // Get original transaction ID (for tracking renewals)
        $originalTransactionId = $latestTransaction['original_transaction_id'] ?? $latestTransaction['transaction_id'] ?? null;
        
        // Get expiry date
        $expiresDateMs = $latestTransaction['expires_date_ms'] ?? null;
        $expiresDate = $expiresDateMs ? date('Y-m-d H:i:s', (int)($expiresDateMs / 1000)) : null;

        // Check if auto-renewable
        $isAutoRenewable = isset($pendingRenewalInfo[0]) && 
                          ($pendingRenewalInfo[0]['auto_renew_status'] ?? 0) == 1;

        return [
            'transaction_id' => $latestTransaction['transaction_id'] ?? null,
            'original_transaction_id' => $originalTransactionId,
            'product_id' => $latestTransaction['product_id'] ?? null,
            'expires_date' => $expiresDate,
            'expires_date_ms' => $expiresDateMs,
            'is_trial_period' => ($latestTransaction['is_trial_period'] ?? 'false') === 'true',
            'is_in_intro_offer_period' => ($latestTransaction['is_in_intro_offer_period'] ?? 'false') === 'true',
            'is_auto_renewable' => $isAutoRenewable,
            'all_transactions' => $latestReceiptInfo, // For restore purchases
        ];
    }

    /**
     * Get human-readable status message
     * 
     * @param int $status Apple status code
     * @return string Status message
     */
    private function getStatusMessage(int $status): string
    {
        $messages = [
            21000 => 'The App Store could not read the receipt data',
            21002 => 'The receipt data was malformed',
            21003 => 'The receipt could not be authenticated',
            21004 => 'The shared secret does not match',
            21005 => 'The receipt server is not available',
            21006 => 'This receipt is valid but the subscription has expired',
            21007 => 'This receipt is from the test environment, but it was sent to the production environment',
            21008 => 'This receipt is from the production environment, but it was sent to the test environment',
            21010 => 'This receipt could not be authorized',
        ];

        return $messages[$status] ?? "Unknown error (status: {$status})";
    }

    /**
     * Verify transaction hasn't been used before (prevent duplicate activations)
     * 
     * @param string $transactionId Apple transaction ID
     * @return bool True if transaction is new, false if already used
     */
    public function isTransactionNew(string $transactionId): bool
    {
        return \App\Models\Subscription::where('apple_transaction_id', $transactionId)->doesntExist();
    }
}

