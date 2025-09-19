<?php



namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductDeliveryOptionRequest;
use App\Services\ProductDeliveryOptionService;
use Exception;
use Illuminate\Support\Facades\Log;

class ProductDeliveryOptionController extends Controller
{
    private $service;

    public function __construct(ProductDeliveryOptionService $service)
    {
        $this->service = $service;
    }

    public function attach(ProductDeliveryOptionRequest $request, $productId)
    {
        try {
            $this->service->attach($productId, $request->validated()['delivery_option_ids']);
            return ResponseHelper::success("Delivery options linked successfully.");
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
