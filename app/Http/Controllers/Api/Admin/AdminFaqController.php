<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminFaqController extends Controller
{
    /**
     * Get all FAQ categories
     */
    public function getCategories()
    {
        try {
            $categories = FaqCategory::withCount('faqs')->get();

            $categories->transform(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'video' => $category->video,
                    'is_active' => $category->is_active,
                    'faqs_count' => $category->faqs_count,
                    'created_at' => $category->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $category->updated_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success($categories, 'FAQ categories retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get FAQs by category (general, buyer, seller)
     */
    public function getFaqsByCategory(Request $request, $category)
    {
        try {
            // Validate category
            $validCategories = ['general', 'buyer', 'seller'];
            if (!in_array($category, $validCategories)) {
                return ResponseHelper::error('Invalid category. Must be: general, buyer, or seller', 400);
            }

            $query = Faq::with('category')
                ->whereHas('category', function ($q) use ($category) {
                    $q->where('title', $category);
                });

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('question', 'like', "%{$search}%")
                      ->orWhere('answer', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('is_active', $request->status === 'active');
            }

            $faqs = $query->latest()->paginate(15);

            $faqs->getCollection()->transform(function ($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'is_active' => $faq->is_active,
                    'category' => [
                        'id' => $faq->category->id,
                        'title' => $faq->category->title,
                        'video' => $faq->category->video
                    ],
                    'created_at' => $faq->created_at->format('d-m-Y H:i:s'),
                    'updated_at' => $faq->updated_at->format('d-m-Y H:i:s')
                ];
            });

            return ResponseHelper::success([
                'faqs' => $faqs,
                'category' => $category,
                'pagination' => [
                    'current_page' => $faqs->currentPage(),
                    'last_page' => $faqs->lastPage(),
                    'per_page' => $faqs->perPage(),
                    'total' => $faqs->total(),
                ]
            ], "FAQs for {$category} category retrieved successfully");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get FAQ statistics
     */
    public function getFaqStatistics()
    {
        try {
            $totalFaqs = Faq::count();
            $activeFaqs = Faq::where('is_active', true)->count();
            $inactiveFaqs = Faq::where('is_active', false)->count();

            $categoriesStats = FaqCategory::withCount('faqs')->get()->map(function ($category) {
                return [
                    'category' => $category->title,
                    'total_faqs' => $category->faqs_count,
                    'active_faqs' => $category->faqs()->where('is_active', true)->count(),
                    'inactive_faqs' => $category->faqs()->where('is_active', false)->count()
                ];
            });

            $stats = [
                'total_faqs' => $totalFaqs,
                'active_faqs' => $activeFaqs,
                'inactive_faqs' => $inactiveFaqs,
                'categories_stats' => $categoriesStats
            ];

            return ResponseHelper::success($stats, 'FAQ statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get FAQ details
     */
    public function getFaqDetails($id)
    {
        try {
            $faq = Faq::with('category')->findOrFail($id);

            $faqData = [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'is_active' => $faq->is_active,
                'category' => [
                    'id' => $faq->category->id,
                    'title' => $faq->category->title,
                    'video' => $faq->category->video,
                    'is_active' => $faq->category->is_active
                ],
                'created_at' => $faq->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $faq->updated_at->format('d-m-Y H:i:s')
            ];

            return ResponseHelper::success($faqData, 'FAQ details retrieved successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error('FAQ not found', 404);
        }
    }

    /**
     * Create new FAQ
     */
    public function createFaq(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'faq_category_id' => 'required|exists:faq_categories,id',
                'question' => 'required|string|max:1000',
                'answer' => 'required|string|max:5000',
                'is_active' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $faq = Faq::create([
                'faq_category_id' => $request->faq_category_id,
                'question' => $request->question,
                'answer' => $request->answer,
                'is_active' => $request->is_active ?? true
            ]);

            $faq->load('category');

            return ResponseHelper::success([
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'is_active' => $faq->is_active,
                'category' => [
                    'id' => $faq->category->id,
                    'title' => $faq->category->title
                ]
            ], 'FAQ created successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update FAQ
     */
    public function updateFaq(Request $request, $id)
    {
        try {
            $faq = Faq::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'faq_category_id' => 'sometimes|exists:faq_categories,id',
                'question' => 'sometimes|string|max:1000',
                'answer' => 'sometimes|string|max:5000',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $faq->update($request->all());
            $faq->load('category');

            return ResponseHelper::success([
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'is_active' => $faq->is_active,
                'category' => [
                    'id' => $faq->category->id,
                    'title' => $faq->category->title
                ]
            ], 'FAQ updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete FAQ
     */
    public function deleteFaq($id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->delete();

            return ResponseHelper::success(null, 'FAQ deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Bulk action on FAQs
     */
    public function bulkAction(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'faq_ids' => 'required|array',
                'action' => 'required|string|in:activate,deactivate,delete'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $faqIds = $request->faq_ids;
            $action = $request->action;

            if ($action === 'activate') {
                Faq::whereIn('id', $faqIds)->update(['is_active' => true]);
                $message = "FAQs activated successfully";
            } elseif ($action === 'deactivate') {
                Faq::whereIn('id', $faqIds)->update(['is_active' => false]);
                $message = "FAQs deactivated successfully";
            } else {
                Faq::whereIn('id', $faqIds)->delete();
                $message = "FAQs deleted successfully";
            }

            return ResponseHelper::success(null, $message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update FAQ category
     */
    public function updateCategory(Request $request, $id)
    {
        try {
            $category = FaqCategory::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'video' => 'nullable|string|max:1000',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $category->update($request->all());

            return ResponseHelper::success([
                'id' => $category->id,
                'title' => $category->title,
                'video' => $category->video,
                'is_active' => $category->is_active,
                'faqs_count' => $category->faqs()->count()
            ], 'FAQ category updated successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
