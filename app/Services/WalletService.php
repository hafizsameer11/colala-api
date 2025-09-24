<?php 
namespace App\Services;
use App\Models\Wallet;
use App\Helpers\ActivityHelper;
class WalletService{
    public function create($data){
        $wallet=Wallet::create($data);
        ActivityHelper::log($data['user_id'], "Wallet created with initial balances.");
        return $wallet;
    }
}