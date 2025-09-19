<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandCreateUpdateRequest;
use App\Services\BrandService;
use Exception;
use Illuminate\Support\Facades\Log;

class BrandController extends Controller
{
    private $service;

    public function __construct(BrandService $service)
    {
        $this->service = $service;
    }

    public function getAll()
    {
        try {
            return ResponseHelper::success($this->service->getAll());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function create(BrandCreateUpdateRequest $request)
    {
        try {
            return ResponseHelper::success($this->service->create($request->validated()));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(BrandCreateUpdateRequest $request, $id)
    {
        try {
            return ResponseHelper::success($this->service->update($id, $request->validated()));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return ResponseHelper::success($this->service->delete($id));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
