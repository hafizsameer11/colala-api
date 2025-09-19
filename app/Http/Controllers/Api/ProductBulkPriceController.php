<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductBulkPriceRequest;
use App\Services\ProductBulkPriceService;
use Exception;
use Illuminate\Support\Facades\Log;

class ProductBulkPriceController extends Controller
{
    private $service;

    public function __construct(ProductBulkPriceService $service)
    {
        $this->service = $service;
    }

    public function store(ProductBulkPriceRequest $request, $productId)
    {
        try {
            $this->service->store($productId, $request->validated()['prices']);
            return ResponseHelper::success("Bulk prices saved successfully.");
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
