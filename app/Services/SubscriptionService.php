<?php 

// app/Services/SubscriptionService.php
namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SubscriptionService
{
    public function subscribe(int $storeId, SubscriptionPlan $plan, string $paymentMethod, string $transactionRef = null): Subscription
    {
        $start = Carbon::today();
        $end   = $start->copy()->addDays($plan->duration_days);
        $user=Auth::user();
        $user=User::where('id',$user->id)->first();
        $user->plan=$plan->name;
        
        // Mark free trial as claimed on first subscription (if not already claimed)
        if (!$user->is_free_trial_claimed) {
            $user->is_free_trial_claimed = true;
        }
        
        $user->save();

        return Subscription::create([
            'store_id'       => $storeId,
            'plan_id'        => $plan->id,
            'start_date'     => $start,
            'end_date'       => $end,
            'status'         => 'active',
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid', // assume success after gateway callback
            'transaction_ref'=> $transactionRef,
        ]);
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'cancelled']);
        return $subscription;
    }

    public function checkAndExpire(): void
    {
        Subscription::where('status','active')
            ->whereDate('end_date','<', now()->toDateString())
            ->update(['status' => 'expired']);
    }
}
