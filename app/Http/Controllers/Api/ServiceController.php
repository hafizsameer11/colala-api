<?php 


namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceCreateUpdateRequest;
use App\Services\ServiceService;
use Exception;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    private $serviceService;

    public function __construct(ServiceService $serviceService)
    {
        $this->serviceService = $serviceService;
    }

    public function create(ServiceCreateUpdateRequest $request)
    {
        try {
            return ResponseHelper::success(
                $this->serviceService->create($request->validated())
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(ServiceCreateUpdateRequest $request, $id)
    {
        try {
            return ResponseHelper::success(
                $this->serviceService->update($id, $request->validated())
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function getAll()
    {
        try {
            return ResponseHelper::success($this->serviceService->getAll());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function getById($id)
    {
        try {
            return ResponseHelper::success($this->serviceService->getById($id));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return ResponseHelper::success($this->serviceService->delete($id));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function relatedServices($categoryId)
    {
        try {
            return ResponseHelper::success($this->serviceService->relatedServices($categoryId));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
