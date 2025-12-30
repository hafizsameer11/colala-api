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
    
        return ResponseHelper::success($categories);
    } catch(Exception $e) {
        Log::error($e->getMessage());
        return ResponseHelper::error($e->getMessage());
    }
}

}
