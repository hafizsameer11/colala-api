<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryCreateUpdateRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    private $categoryService;
    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }
    public function create(CategoryCreateUpdateRequest $request){
        try{
            $data=$request->validated();
            $categoruy=$this->categoryService->create($data);
            return ResponseHelper::success($categoruy);

        }catch(Exception $e){
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }

    }
    public function update(CategoryCreateUpdateRequest $request, $id){
        try{
            $data=$request->validated();
            $categoruy=$this->categoryService->update($id, $data);
            return ResponseHelper::success($categoruy);

        }catch(Exception $e){
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
   public function getAll()
{
    try {
        $categories = Category::with('children')->withCount('products')->whereNull('parent_id')->orderBy('created_at', 'desc')->get();
        
        // Reorder to put "service" or "services" category second
        $serviceCategory = $categories->first(function ($category) {
            return stripos($category->title ?? '', 'Services') !== false;
        });
        
        if ($serviceCategory && $categories->count() > 1) {
            // Remove service category from its current position
            $otherCategories = $categories->reject(function ($category) use ($serviceCategory) {
                return $category->id === $serviceCategory->id;
            })->values();
            
            // Build new collection: first item, then service, then rest
            $reordered = collect();
            
            // Add first category (keep it first)
            if ($otherCategories->count() > 0) {
                $reordered->push($otherCategories->first());
            }
            
            // Add service category second
            $reordered->push($serviceCategory);
            
            // Add remaining categories
            if ($otherCategories->count() > 1) {
                $reordered = $reordered->concat($otherCategories->slice(1));
            }
            
            $categories = $reordered;
        }
    
        return ResponseHelper::success($categories->values());
    } catch(Exception $e) {
        Log::error($e->getMessage());
        return ResponseHelper::error($e->getMessage());
    }
}

}
