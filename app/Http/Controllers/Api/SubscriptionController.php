<?php
// app/Http/Controllers/Api/SubscriptionController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\Store;
use App\Models\StoreUser;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use App\Services\AppleReceiptValidationService;
use App\Services\AppleWebhookService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    private SubscriptionService $service;
    private AppleReceiptValidationService $appleValidationService;
    private AppleWebhookService $appleWebhookService;

    public function __construct(
        SubscriptionService $service,
        AppleReceiptValidationService $appleValidationService,
        AppleWebhookService $appleWebhookService
    ) {
        $this->service = $service;
        $this->appleValidationService = $appleValidationService;
        $this->appleWebhookService = $appleWebhookService;
    }

    protected function userStore(): Store {
        $store = Store::where('user_id', Auth::id())->first();
        if(!$store){
            $storeUser = StoreUser::where('user_id', Auth::user()->id)->first();
            if($storeUser){
                $store = $storeUser->store;
            }
        }
        if(!$store){
            throw new Exception('Store not found');
        }
        return $store;
    }

    public function plans() {
        return ResponseHelper::success(SubscriptionPlanResource::collection(SubscriptionPlan::all()));
    }

    public function mySubscriptions() {
        $store = $this->userStore();
        $subs  = Subscription::with('plan')->where('store_id',$store->id)->latest()->get();
        return ResponseHelper::success(SubscriptionResource::collection($subs));
    }

    public function subscribe(SubscriptionRequest $request) {
        try {
            $store = $this->userStore();
            $plan  = SubscriptionPlan::findOrFail($request->plan_id);

            // Handle Apple IAP
            if ($request->payment_method === 'apple_iap') {
                return $this->handleAppleIAPSubscription($request, $store, $plan);
            }

            // Call payment gateway logic here → after success callback call service
            $subscription = $this->service->subscribe($store->id, $plan, $request->payment_method);

            return ResponseHelper::success(new SubscriptionResource($subscription), "Subscription successful");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed to subscribe: ".$e->getMessage());
        }
    }

    /**
     * Handle Apple IAP subscription
     */
    private function handleAppleIAPSubscription(Request $request, Store $store, SubscriptionPlan $plan)
    {
        $request->validate([
            'receipt_data' => 'required|string',
            'transaction_id' => 'required|string',
            'original_transaction_id' => 'required|string',
            'product_id' => 'required|string',
        ]);

        $receiptData = $request->receipt_data;
        $transactionId = $request->transaction_id;
        $originalTransactionId = $request->original_transaction_id;
        $productId = $request->product_id;

        // Verify product ID matches plan
        $billingPeriod = $request->billing_period ?? 'monthly';
        $expectedProductId = $billingPeriod === 'annual' 
            ? $plan->apple_product_id_annual 
            : $plan->apple_product_id_monthly;

        if ($productId !== $expectedProductId) {
            return ResponseHelper::error('Product ID does not match selected plan', 400);
        }

        // Check if transaction already used
        if (!$this->appleValidationService->isTransactionNew($transactionId)) {
            return ResponseHelper::error('Transaction already used', 400);
        }

        // Validate receipt with Apple
        try {
            $isSandbox = config('services.apple.use_sandbox', false);
            $receiptResponse = $this->appleValidationService->validateReceipt($receiptData, $isSandbox);
            $subscriptionInfo = $this->appleValidationService->extractSubscriptionInfo($receiptResponse);
        } catch (Exception $e) {
            Log::error('Apple receipt validation failed', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Invalid receipt: ' . $e->getMessage(), 400);
        }

        // Verify transaction IDs match
        if ($subscriptionInfo['transaction_id'] !== $transactionId ||
            $subscriptionInfo['original_transaction_id'] !== $originalTransactionId) {
            return ResponseHelper::error('Transaction IDs do not match receipt', 400);
        }

        // Create or update subscription
        DB::beginTransaction();
        try {
            // Check if subscription already exists for this original transaction
            $existingSubscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)
                ->where('store_id', $store->id)
                ->first();

            if ($existingSubscription) {
                // Update existing subscription
                $existingSubscription->update([
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'apple_transaction_id' => $transactionId,
                    'apple_receipt_data' => $receiptData,
                    'is_auto_renewable' => $subscriptionInfo['is_auto_renewable'],
                    'payment_method' => 'apple_iap',
                    'payment_status' => 'paid',
                ]);

                // Update end date if provided
                if ($subscriptionInfo['expires_date']) {
                    $existingSubscription->update([
                        'end_date' => $subscriptionInfo['expires_date']
                    ]);
                }

                $subscription = $existingSubscription->fresh();
            } else {
                // Create new subscription
                $subscription = $this->service->subscribe($store->id, $plan, 'apple_iap');
                
                // Update with Apple-specific data
                $subscription->update([
                    'apple_transaction_id' => $transactionId,
                    'apple_original_transaction_id' => $originalTransactionId,
                    'apple_receipt_data' => $receiptData,
                    'is_auto_renewable' => $subscriptionInfo['is_auto_renewable'],
                ]);

                // Update end date if provided by Apple
                if ($subscriptionInfo['expires_date']) {
                    $subscription->update([
                        'end_date' => $subscriptionInfo['expires_date']
                    ]);
                }
            }

            DB::commit();

            return ResponseHelper::success([
                'subscription' => new SubscriptionResource($subscription)
            ], 'Subscription activated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating Apple IAP subscription', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Failed to activate subscription: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate Apple receipt and activate subscription
     * POST /seller/subscriptions/validate-receipt
     */
    public function validateReceipt(Request $request)
    {
        try {
            $request->validate([
                'receipt_data' => 'required|string',
                'transaction_id' => 'required|string',
                'original_transaction_id' => 'required|string',
                'plan_id' => 'required|exists:subscription_plans,id',
                'billing_period' => 'required|in:monthly,annual',
                'product_id' => 'required|string',
            ]);

            $store = $this->userStore();
            $plan = SubscriptionPlan::findOrFail($request->plan_id);

            // Verify product ID matches plan
            $expectedProductId = $request->billing_period === 'annual' 
                ? $plan->apple_product_id_annual 
                : $plan->apple_product_id_monthly;

            if ($request->product_id !== $expectedProductId) {
                return ResponseHelper::error('Product ID does not match selected plan', 400);
            }

            // Check if transaction already used
            if (!$this->appleValidationService->isTransactionNew($request->transaction_id)) {
                return ResponseHelper::error('Transaction already used', 400);
            }

            // Validate receipt with Apple
            try {
                $isSandbox = config('services.apple.use_sandbox', false);
                $receiptResponse = $this->appleValidationService->validateReceipt($request->receipt_data, $isSandbox);
                $subscriptionInfo = $this->appleValidationService->extractSubscriptionInfo($receiptResponse);
            } catch (Exception $e) {
                return ResponseHelper::error('Invalid receipt: ' . $e->getMessage(), 400);
            }

            // Verify transaction IDs match
            if ($subscriptionInfo['transaction_id'] !== $request->transaction_id ||
                $subscriptionInfo['original_transaction_id'] !== $request->original_transaction_id) {
                return ResponseHelper::error('Transaction IDs do not match receipt', 400);
            }

            // Create or update subscription
            DB::beginTransaction();
            try {
                $existingSubscription = Subscription::where('apple_original_transaction_id', $request->original_transaction_id)
                    ->where('store_id', $store->id)
                    ->first();

                if ($existingSubscription) {
                    $existingSubscription->update([
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'apple_transaction_id' => $request->transaction_id,
                        'apple_receipt_data' => $request->receipt_data,
                        'is_auto_renewable' => $subscriptionInfo['is_auto_renewable'],
                        'payment_method' => 'apple_iap',
                        'payment_status' => 'paid',
                    ]);

                    if ($subscriptionInfo['expires_date']) {
                        $existingSubscription->update(['end_date' => $subscriptionInfo['expires_date']]);
                    }

                    $subscription = $existingSubscription->fresh();
                } else {
                    $subscription = $this->service->subscribe($store->id, $plan, 'apple_iap');
                    $subscription->update([
                        'apple_transaction_id' => $request->transaction_id,
                        'apple_original_transaction_id' => $request->original_transaction_id,
                        'apple_receipt_data' => $request->receipt_data,
                        'is_auto_renewable' => $subscriptionInfo['is_auto_renewable'],
                    ]);

                    if ($subscriptionInfo['expires_date']) {
                        $subscription->update(['end_date' => $subscriptionInfo['expires_date']]);
                    }
                }

                DB::commit();

                return ResponseHelper::success([
                    'subscription' => new SubscriptionResource($subscription)
                ], 'Subscription validated and activated successfully');
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Error validating Apple receipt', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Failed to validate receipt: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle Apple server-to-server webhook notifications
     * POST /seller/subscriptions/apple-webhook
     */
    public function appleWebhook(Request $request)
    {
        try {
            // Apple sends JWT-signed payload
            $signedPayload = $request->input('signedPayload') ?? $request->input('signed_payload');
            
            if (!$signedPayload) {
                // Try to get from header or body
                $signedPayload = $request->header('X-Apple-Notification') ?? $request->getContent();
            }

            if (!$signedPayload) {
                Log::warning('Apple webhook received without signed payload');
                return response()->json(['status' => 'ok'], 200); // Always return 200 to Apple
            }

            // Decode and handle notification
            $notificationPayload = [
                'notification_type' => $request->input('notification_type') ?? $request->input('notificationType'),
                'signedPayload' => $signedPayload,
            ];

            $this->appleWebhookService->handleNotification($notificationPayload);

            // Always return 200 OK to Apple
            return response()->json(['status' => 'ok'], 200);
        } catch (Exception $e) {
            Log::error('Error processing Apple webhook', ['error' => $e->getMessage()]);
            // Always return 200 to Apple even on error
            return response()->json(['status' => 'ok'], 200);
        }
    }

    /**
     * Restore previous Apple IAP purchases
     * POST /seller/subscriptions/restore-purchases
     */
    public function restorePurchases(Request $request)
    {
        try {
            $request->validate([
                'receipt_data' => 'required|string',
            ]);

            $store = $this->userStore();

            // Validate receipt with Apple
            try {
                $isSandbox = config('services.apple.use_sandbox', false);
                $receiptResponse = $this->appleValidationService->validateReceipt($request->receipt_data, $isSandbox);
                $subscriptionInfo = $this->appleValidationService->extractSubscriptionInfo($receiptResponse);
            } catch (Exception $e) {
                return ResponseHelper::error('Invalid receipt: ' . $e->getMessage(), 400);
            }

            // Get all transactions from receipt
            $allTransactions = $subscriptionInfo['all_transactions'] ?? [];
            $restoredSubscriptions = [];

            foreach ($allTransactions as $transaction) {
                $originalTransactionId = $transaction['original_transaction_id'] ?? $transaction['transaction_id'] ?? null;
                
                if (!$originalTransactionId) {
                    continue;
                }

                // Find subscription by original transaction ID
                $subscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)
                    ->where('store_id', $store->id)
                    ->first();

                if ($subscription) {
                    $restoredSubscriptions[] = new SubscriptionResource($subscription);
                }
            }

            return ResponseHelper::success([
                'subscriptions' => $restoredSubscriptions
            ], 'Purchases restored successfully');
        } catch (Exception $e) {
            Log::error('Error restoring purchases', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Failed to restore purchases: ' . $e->getMessage(), 500);
        }
    }

    public function cancel(Subscription $subscription) {
        $store = $this->userStore();
        abort_if($subscription->store_id !== $store->id, 403, "Not your subscription");

        $this->service->cancel($subscription);

        return ResponseHelper::success(new SubscriptionResource($subscription), "Subscription cancelled");
    }
}
