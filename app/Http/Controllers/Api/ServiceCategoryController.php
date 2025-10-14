<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceCategoryRequest;
use App\Models\ServiceCategory;
use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        try {
            return ResponseHelper::success(ServiceCategory::with('services')->get());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function store(ServiceCategoryRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('service_categories', 'public');
            }
            $category = ServiceCategory::create($data);

            return ResponseHelper::success($category, 'Category created successfully.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $category = ServiceCategory::with('services.store','services.media','services.subServices')->findOrFail($id);
            return ResponseHelper::success($category);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(ServiceCategoryRequest $request, $id)
    {
        try {
            $category = ServiceCategory::findOrFail($id);
            $data     = $request->validated();

            if ($request->hasFile('image')) {
                // delete old image if exists
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }
                $data['image'] = $request->file('image')->store('service_categories', 'public');
            }

            $category->update($data);
            return ResponseHelper::success($category, 'Category updated successfully.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $category = ServiceCategory::findOrFail($id);
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $category->delete();
            return ResponseHelper::success(null, 'Category deleted successfully.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Extra route: attach an existing service to an existing category.
     */
    public function attachService($categoryId, $serviceId)
    {
        try {
            $category = ServiceCategory::findOrFail($categoryId);
            $service  = Service::findOrFail($serviceId);

            $service->update(['service_category_id' => $category->id]);

            return ResponseHelper::success(
                $service->load('serviceCategory'),
                'Service attached to category successfully.'
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
