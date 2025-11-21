<?php

namespace App\Http\Controllers;

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
                $payout->status = $data["status"] === "SUCCESSFUL" ? "approved" : "rejected";
                $payout->flutterwave_transfer_id = $data["id"] ?? $payout->flutterwave_transfer_id;
                $payout->remarks = $data["complete_message"] ?? $data["message"] ?? "";
                $payout->save();

                Log::info('Flutterwave webhook: Payout updated', [
                    'reference' => $data["reference"],
                    'status' => $payout->status
                ]);
            } else {
                Log::warning('Flutterwave webhook: Payout not found', [
                    'reference' => $data["reference"] ?? 'unknown'
                ]);
            }
        }

        return response("OK", 200);
    }
}

