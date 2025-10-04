<?php

// app/Http/Controllers/BoostProductController.php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BoostProductRequest;
use App\Http\Resources\BoostProductResource;
use App\Models\BoostProduct;
use App\Models\Product;
use App\Models\Store;
use App\Services\BoostProductService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BoostProductController extends Controller
{
    public function __construct(private BoostProductService $svc) {}

    protected function userStore(): Store
    {
        $store = Store::where('user_id', Auth::id())->first();
        abort_if(!$store, Response::HTTP_UNPROCESSABLE_ENTITY, 'No store linked to this user.');
        return $store;
    }

    protected function assertProductBelongsToStore(int $productId, int $storeId): void
    {
        $exists = Product::where('id', $productId)->where('store_id', $storeId)->exists();
        abort_if(!$exists, Response::HTTP_FORBIDDEN, 'Product does not belong to your store.');
    }

    // GET /api/boosts?status=running
    public function index(Request $request)
    {
        try {
            $store = $this->userStore();

          $q = BoostProduct::with(['product.images', 'store'])
    ->forStore($store->id)
    ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
    ->latest();


            $data = BoostProductResource::collection($q->paginate(20));
            return ResponseHelper::success($data, "data retrived succesfuly");
        } catch (Exception $e) {
            return ResponseHelper::error("Something Went wrong" . $e->getMessage());
        }
    }

    // POST /api/boosts/preview
    public function preview(BoostProductRequest $request)
    {
        try {
            $store = $this->userStore();
            $this->assertProductBelongsToStore((int)$request->product_id, $store->id);

            $calc = $this->svc->computeTotals((int)$request->budget, (int)$request->duration);
            $product=Product::with(['images', 'store'])->find((int)$request->product_id);
            
            return response()->json([
                'product'      => $product,
                'daily_budget' => (int)$request->budget,
                'duration'     => (int)$request->duration,
                'subtotal'     => $calc['subtotal'],
                'platform_fee' => $calc['platform_fee'],
                'total'        => $calc['total'],
                'estimated'    => [
                    'reach'  => $calc['reach'],
                    'clicks' => $calc['est_clicks'],
                    'cpc'    => $calc['est_cpc'],
                ],
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error("Something Went wrong" . $e->getMessage());
        }
    }

    // POST /api/boosts
    public function store(BoostProductRequest $request)
    {
        $store = $this->userStore();
        $this->assertProductBelongsToStore((int)$request->product_id, $store->id);

        return DB::transaction(function () use ($request, $store) {
            $boost = $this->svc->create($request->validated(), $store->id);

            // — Payment hook (example)
            if ($boost->payment_method === 'wallet') {
                // WalletService::charge($store->id, $boost->total_amount, "Boost #{$boost->id}");
                $boost->payment_status = 'paid';
                $boost->save();
            }

            return new BoostProductResource($boost);
        });
    }

    public function show(BoostProduct $boost)
    {
        try {
            $store = $this->userStore();
            abort_if($boost->store_id !== $store->id, Response::HTTP_FORBIDDEN);
            $data = new BoostProductResource($boost);
            return ResponseHelper::success($data, "data retrived succesfuly");
        } catch (Exception $e) {
            return ResponseHelper::error("Something Went wrong" . $e->getMessage());
        }
    }

    // PATCH /api/boosts/{boost}/status
    public function updateStatus(Request $request, BoostProduct $boost)
    {
        try {
            $store = $this->userStore();
            abort_if($boost->store_id !== $store->id, Response::HTTP_FORBIDDEN);

            $data = $request->validate(['action' => 'required|in:pause,resume,cancel,complete']);
            $map = ['pause' => 'paused', 'resume' => 'running', 'cancel' => 'cancelled', 'complete' => 'completed'];

            $boost->update(['status' => $map[$data['action']]]);

            $data = new BoostProductResource($boost);
            return ResponseHelper::success($data, "data retrived succesfuly");
        } catch (Exception $e) {
            return ResponseHelper::error("Something Went wrong" . $e->getMessage());
        }
    }

    // PATCH /api/boosts/{boost}/metrics
    public function updateMetrics(Request $request, BoostProduct $boost)
    {
        $store = $this->userStore();
        abort_if($boost->store_id !== $store->id, Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'impressions' => 'nullable|integer|min:0',
            'clicks'      => 'nullable|integer|min:0',
        ]);

        $boost->fill($data);

        if (($boost->clicks ?? 0) > 0 && $boost->total_amount > 0) {
            $boost->cpc = round($boost->total_amount / max(1, $boost->clicks), 2);
        }

        $boost->save();
        $data = new BoostProductResource($boost);

        return ResponseHelper::success($data, "data retrived succesfuly");
    }
}
