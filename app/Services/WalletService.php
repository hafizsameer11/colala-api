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
    public function getBalance($user_id){
        $wallet=Wallet::where('user_id',$user_id)->first();
        if(!$wallet){
            $wallet=$this->create(['user_id'=>$user_id,'shopping_balance'=>0,'reward_balance'=>0,'loyality_points'=>0]);
        }
        return $wallet;
    }
}