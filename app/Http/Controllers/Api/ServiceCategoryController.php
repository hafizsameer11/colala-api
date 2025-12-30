<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceCategoryRequest;
use App\Models\ServiceCategory;
use App\Models\Service;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $page = $request->input('page', 1);
            
            // Validate per_page (max 100)
            $perPage = min(max((int)$perPage, 1), 100);
            $page = max((int)$page, 1);

            $query = ServiceCategory::withCount('services')
                ->orderBy('created_at', 'desc');

            // Filter by is_active if provided
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // If per_page is provided, use pagination, otherwise return all
            if ($request->has('per_page') || $request->has('page')) {
                $categories = $query->paginate($perPage, ['*'], 'page', $page);
                
                // Transform the data to include image URLs
                $categories->getCollection()->transform(function ($category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'image' => $category->image,
                        'image_url' => $category->image ? asset('storage/' . $category->image) : null,
                        'is_active' => $category->is_active,
                        'services_count' => $category->services_count,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                    ];
                });

                return ResponseHelper::success([
                    'data' => $categories->items(),
                    'current_page' => $categories->currentPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'last_page' => $categories->lastPage(),
                    'from' => $categories->firstItem(),
                    'to' => $categories->lastItem(),
                ]);
            } else {
                // Return all categories without pagination
                $categories = $query->get();
                
                $transformed = $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'image' => $category->image,
                        'image_url' => $category->image ? asset('storage/' . $category->image) : null,
                        'is_active' => $category->is_active,
                        'services_count' => $category->services_count,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                    ];
                });

                return ResponseHelper::success($transformed);
            }
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

            // Return with image_url
            $createdCategory = [
                'id' => $category->id,
                'title' => $category->title,
                'image' => $category->image,
                'image_url' => $category->image ? asset('storage/' . $category->image) : null,
                'is_active' => $category->is_active,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];

            return ResponseHelper::success($createdCategory, 'Category created successfully.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $category = ServiceCategory::with('services.store','services.media','services.subServices')
                ->withCount('services')
                ->findOrFail($id);
            
            // Transform to include image_url
            $transformed = [
                'id' => $category->id,
                'title' => $category->title,
                'image' => $category->image,
                'image_url' => $category->image ? asset('storage/' . $category->image) : null,
                'is_active' => $category->is_active,
                'services_count' => $category->services_count,
                'services' => $category->services,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
            
            return ResponseHelper::success($transformed);
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
            
            // Return updated category with image URL
            $updatedCategory = [
                'id' => $category->id,
                'title' => $category->title,
                'image' => $category->image,
                'image_url' => $category->image ? asset('storage/' . $category->image) : null,
                'is_active' => $category->is_active,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
            
            return ResponseHelper::success($updatedCategory, 'Category updated successfully.');
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
