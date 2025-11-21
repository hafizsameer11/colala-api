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
        return Http::withToken($this->secret)
            ->post("https://api.flutterwave.com/v3/accounts/resolve", [
                "account_number" => $accountNumber,
                "account_bank"   => $bankCode
            ])
            ->json();
    }

    public function makeTransfer($bankCode, $accountNumber, $amount, $reference, $narration = "Wallet Payout")
    {
        return Http::withToken($this->secret)
            ->post("https://api.flutterwave.com/v3/transfers", [
                "account_bank"   => $bankCode,
                "account_number" => $accountNumber,
                "amount"         => $amount,
                "currency"       => "NGN",
                "reference"      => $reference,
                "narration"      => $narration
            ])
            ->json();
    }
}

