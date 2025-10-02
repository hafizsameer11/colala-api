<?php
// app/Http/Controllers/Api/SubscriptionController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    private SubscriptionService $service;

    public function __construct(SubscriptionService $service) {
        $this->service = $service;
    }

    protected function userStore(): Store {
        return Store::where('user_id', Auth::id())->firstOrFail();
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

            // Call payment gateway logic here → after success callback call service
            $subscription = $this->service->subscribe($store->id, $plan, $request->payment_method);

            return ResponseHelper::success(new SubscriptionResource($subscription), "Subscription successful");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed to subscribe: ".$e->getMessage());
        }
    }

    public function cancel(Subscription $subscription) {
        $store = $this->userStore();
        abort_if($subscription->store_id !== $store->id, 403, "Not your subscription");

        $this->service->cancel($subscription);

        return ResponseHelper::success(new SubscriptionResource($subscription), "Subscription cancelled");
    }
}
