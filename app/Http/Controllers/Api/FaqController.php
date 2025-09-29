<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\{FaqCategory, Faq};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class FaqController extends Controller
{
    // List all categories with their FAQs (public endpoint)
    public function index()
    {
        try {
            $categories = FaqCategory::with(['faqs' => function ($q) {
                $q->where('is_active', true);
            }])
            ->where('is_active', true)
            ->latest()
            ->get();

            return ResponseHelper::success($categories);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function showByCategoryName(Request $request, string $name)
{
    try {
        // find category by name (case-insensitive)
        $category = FaqCategory::where('title', $name)
            ->where('is_active', true)
            ->firstOrFail();

        // get its active faqs separately
        $faqs = Faq::where('faq_category_id', $category->id)
            ->where('is_active', true)
            ->latest()
            ->get();

        return ResponseHelper::success([
            'category' => $category,
            'faqs'     => $faqs
        ]);
    } catch (Exception $e) {
        Log::error($e->getMessage());
        return ResponseHelper::error($e->getMessage());
    }
}


    // Create a category (admin)
    public function storeCategory(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video' => 'nullable|string'
        ]);

        try {
            $category = FaqCategory::create($request->only('title','video','is_active'));
            return ResponseHelper::success($category, 'Category created successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // Update a category
    public function updateCategory(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video' => 'nullable|string'
        ]);

        try {
            $category = FaqCategory::findOrFail($id);
            $category->update($request->only('title','video','is_active'));
            return ResponseHelper::success($category, 'Category updated successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // Delete a category
    public function destroyCategory($id)
    {
        try {
            FaqCategory::findOrFail($id)->delete();
            return ResponseHelper::success(null, 'Category deleted successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // Create an FAQ inside a category
    public function storeFaq(Request $request)
    {
        $request->validate([
            'faq_category_id' => 'required|exists:faq_categories,id',
            'question'        => 'required|string|max:255',
            'answer'          => 'nullable|string'
        ]);

        try {
            $faq = Faq::create($request->only('faq_category_id','question','answer','is_active'));
            return ResponseHelper::success($faq, 'FAQ created successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // Update an FAQ
    public function updateFaq(Request $request, $id)
    {
        $request->validate([
            'question' => 'required|string|max:255',
            'answer'   => 'nullable|string'
        ]);

        try {
            $faq = Faq::findOrFail($id);
            $faq->update($request->only('faq_category_id','question','answer','is_active'));
            return ResponseHelper::success($faq, 'FAQ updated successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }

    // Delete an FAQ
    public function destroyFaq($id)
    {
        try {
            Faq::findOrFail($id)->delete();
            return ResponseHelper::success(null, 'FAQ deleted successfully');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage());
        }
    }
}
