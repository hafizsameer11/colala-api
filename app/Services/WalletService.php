<?php 
namespace App\Services;
use App\Models\Wallet;
use App\Helpers\ActivityHelper;
use App\Models\Transaction;

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
    public function topUp($user_id,$amount){
        $wallet=$this->getBalance($user_id);
        $wallet->shopping_balance +=$amount;
        $wallet->save();
        //create transaction record
        $transaction=Transaction::create([
            'tx_id'=>uniqid('tx_'),
            'amount'=>$amount,
            'status'=>'completed',
            'type'=>'deposit',
            'user_id'=>$user_id,
            'order_id'=>null
        ]);
        ActivityHelper::log($user_id, "Wallet topped up by amount: $amount.");
        return $wallet;
    }
}