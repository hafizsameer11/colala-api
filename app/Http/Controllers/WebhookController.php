<?php

namespace App\Http\Controllers;

use App\Helpers\UserNotificationHelper;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function flutterwave(Request $request)
    {
        // VERIFY WEBHOOK SIGNATURE
        $webhookSecret = env('FLUTTERWAVE_WEBHOOK_SECRET');
        
        if ($webhookSecret && $request->header('verif-hash') !== $webhookSecret) {
            Log::warning('Flutterwave webhook: Invalid signature', [
                'received_hash' => $request->header('verif-hash'),
                'expected_hash' => $webhookSecret
            ]);
            return response("Invalid signature", 401);
        }

        if ($request->event === "transfer.completed") {
            $data = $request->data;

            $payout = WithdrawalRequest::where("reference", $data["reference"])->first();

            if ($payout) {
                $oldStatus = $payout->status;
                $payout->status = $data["status"] === "SUCCESSFUL" ? "approved" : "rejected";
                $payout->flutterwave_transfer_id = $data["id"] ?? $payout->flutterwave_transfer_id;
                $payout->remarks = $data["complete_message"] ?? $data["message"] ?? "";
                // Save complete webhook payload as JSON
                $payout->webhook_data = $request->all();
                $payout->save();

                // Send notification to user
                $statusMessage = $payout->status === "approved" 
                    ? "Your withdrawal of ₦" . number_format($payout->amount, 2) . " has been approved and transferred successfully."
                    : "Your withdrawal of ₦" . number_format($payout->amount, 2) . " was rejected. " . ($payout->remarks ?: "Please contact support.");

                UserNotificationHelper::notify(
                    $payout->user_id,
                    $payout->status === "approved" ? 'Withdrawal Approved' : 'Withdrawal Rejected',
                    $statusMessage,
                    [
                        'type' => 'withdrawal_status_update',
                        'withdrawal_id' => $payout->id,
                        'amount' => $payout->amount,
                        'status' => $payout->status,
                        'reference' => $payout->reference,
                        'remarks' => $payout->remarks
                    ]
                );

                Log::info('Flutterwave webhook: Payout updated', [
                    'reference' => $data["reference"],
                    'status' => $payout->status
                ]);
            } else {
                Log::warning('Flutterwave webhook: Payout not found', [
                    'reference' => $data["reference"] ?? 'unknown',
                    'webhook_payload' => $request->all()
                ]);
            }
        } else {
            // Log other webhook events for debugging
            Log::info('Flutterwave webhook: Other event received', [
                'event' => $request->event,
                'webhook_payload' => $request->all()
            ]);
        }

        return response("OK", 200);
    }
}

