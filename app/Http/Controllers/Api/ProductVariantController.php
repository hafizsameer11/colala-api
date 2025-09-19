<?php
namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariantRequest;
use App\Services\ProductVariantService;
use Exception;
use Illuminate\Support\Facades\Log;

class ProductVariantController extends Controller
{
    private $variantService;
    public function __construct(ProductVariantService $variantService)
    {
        $this->variantService = $variantService;
    }

    public function create(ProductVariantRequest $request, $productId)
    {
        try {
            return ResponseHelper::success($this->variantService->create($productId, $request->validated()));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(ProductVariantRequest $request, $productId, $variantId)
    {
        try {
            return ResponseHelper::success($this->variantService->update($productId, $variantId, $request->validated()));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function delete($productId, $variantId)
    {
        try {
            return ResponseHelper::success($this->variantService->delete($productId, $variantId));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
