<?php 
namespace App\Services;
use App\Models\Wallet;
use App\Helpers\ActivityHelper;
use App\Models\Escrow;
use App\Models\Transaction;
use Symfony\Component\HttpKernel\HttpCache\Esi;

class WalletService{
    public function create($data){
        $wallet=Wallet::create($data);
        ActivityHelper::log($data['user_id'], "Wallet created with initial balances.");
        return $wallet;
    }
    public function getBalance($user_id){
        $wallet=Wallet::where('user_id',$user_id)->first();
        if(!$wallet){
            $wallet=$this->create(['user_id'=>$user_id,'shopping_balance'=>0,'reward_balance'=>0,'loyality_points'=>0,'ad_credit'=>0]);
        }
        $escrowBalance=Escrow::where('user_id',$user_id)->where('status','locked')->sum('amount');
        $wallet->escrow_balance=$escrowBalance;
        // $wallet->available_balance=$wallet->shopping_balance-$escrowBalance;
        return $wallet;
    }
    public function topUp($user_id,$amount){
        $wallet=$this->getBalance($user_id);
        
        // Remove the virtual escrow_balance before saving
        unset($wallet->escrow_balance);
        
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
        
        // Re-add escrow_balance for the response
        $escrowBalance=Escrow::where('user_id',$user_id)->where('status','locked')->sum('amount');
        $wallet->escrow_balance=$escrowBalance;
        
        return $wallet;
    }

    public function transferBalance(int $userId, string $from, string $to, int $amount){
        $wallet = $this->getBalance($userId);
        
        // Remove the virtual escrow_balance before saving
        unset($wallet->escrow_balance);
        
        $map = [
            'shopping' => 'shopping_balance',
            'referral' => 'referral_balance',
            'reward'   => 'reward_balance',
        ];
        $fromCol = $map[$from] ?? null;
        $toCol   = $map[$to] ?? null;
        if (!$fromCol || !$toCol) {
            throw new \InvalidArgumentException('Invalid transfer type');
        }
        if ($wallet->$fromCol < $amount) {
            throw new \RuntimeException('Insufficient balance for transfer');
        }
        $wallet->$fromCol -= $amount;
        $wallet->$toCol   += $amount;
        $wallet->save();

        Transaction::create([
            'tx_id'=>uniqid('tx_'),
            'amount'=>$amount,
            'status'=>'completed',
            'type'=>"transfer_{$from}_to_{$to}",
            'user_id'=>$userId,
            'order_id'=>null
        ]);

        ActivityHelper::log($userId, "Transferred {$amount} from {$from} to {$to}.");
        
        // Re-add escrow_balance for the response
        $escrowBalance=Escrow::where('user_id',$userId)->where('status','locked')->sum('amount');
        $wallet->escrow_balance=$escrowBalance;
        
        return $wallet;
    }

    public function transferReferralToShopping(int $userId, int $amount)
    {
        $wallet = $this->getBalance($userId);
        
        // Remove the virtual escrow_balance before saving
        unset($wallet->escrow_balance);
        
        if ($wallet->referral_balance < $amount) {
            throw new \RuntimeException('Insufficient balance for transfer');
        }
        $wallet->referral_balance -= $amount;
        $wallet->shopping_balance += $amount;
        $wallet->save();

        Transaction::create([
            'tx_id'   => uniqid('tx_'),
            'amount'  => $amount,
            'status'  => 'completed',
            'type'    => 'deposit',
            'user_id' => $userId,
            'order_id'=> null,
        ]);
        ActivityHelper::log($userId, "Transferred {$amount} from referral to shopping.");
        
        // Re-add escrow_balance for the response
        $escrowBalance=Escrow::where('user_id',$userId)->where('status','locked')->sum('amount');
        $wallet->escrow_balance=$escrowBalance;
        
        return $wallet;
    }
}