<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FlutterwaveService
{
    private $secret;

    public function __construct()
    {
        $this->secret = env('FLUTTERWAVE_SECRET_KEY');
    }

    public function getBanks($country = 'NG')
    {
        return Http::withToken($this->secret)
            ->get("https://api.flutterwave.com/v3/banks/$country")
            ->json();
    }

    public function resolveAccount($accountNumber, $bankCode)
    {
        // Flutterwave requires bank_code to be numeric string (e.g., "044", "057")
        // Ensure it's a string representation of a number
        $bankCode = (string) preg_replace('/[^0-9]/', '', $bankCode);
        
        return Http::withToken($this->secret)
            ->post("https://api.flutterwave.com/v3/accounts/resolve", [
                "account_number" => (string) $accountNumber,
                "account_bank"   => $bankCode
            ])
            ->json();
    }

    public function makeTransfer($bankCode, $accountNumber, $amount, $reference, $narration = "Wallet Payout")
    {
        // Flutterwave requires bank_code to be numeric string (e.g., "044", "057")
        // Ensure it's a string representation of a number
        $bankCode = (string) preg_replace('/[^0-9]/', '', $bankCode);
        
        return Http::withToken($this->secret)
            ->post("https://api.flutterwave.com/v3/transfers", [
                "account_bank"   => $bankCode,
                "account_number" => (string) $accountNumber,
                "amount"         => (float) $amount,
                "currency"       => "NGN",
                "reference"      => $reference,
                "narration"      => $narration
            ])
            ->json();
    }
}

