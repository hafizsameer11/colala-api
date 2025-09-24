<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCreateUpdateRequest;
use App\Services\ProductService;
use Exception;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    private $productService;
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function getAll()
    {
        try {
            return ResponseHelper::success($this->productService->getAll());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function getAllforBuyer(){
        try {
            return ResponseHelper::success($this->productService->getAllforBuyer());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function create(ProductCreateUpdateRequest $request)
    {
        try {
            $data=$request->validated();
            $product=$this->productService->create($data);
            return ResponseHelper::success($product);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(ProductCreateUpdateRequest $request, $id)
    {
        try {
            return ResponseHelper::success($this->productService->update($id, $request->validated()));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return ResponseHelper::success($this->productService->delete($id));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
