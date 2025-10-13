<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\StoreReview;
use App\Models\Product;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;

class AdminRatingsReviewsController extends Controller
{
    /**
     * Ratings & Reviews summary (cards on top of the page)
     */
    public function summary(Request $request)
    {
        try {
            $totalStoreReviews = StoreReview::count();
            $totalProductReviews = ProductReview::count();
            $avgStoreRating = round(StoreReview::avg('rating') ?? 0, 2);
            $avgProductRating = round(ProductReview::avg('rating') ?? 0, 2);

            return ResponseHelper::success([
                'total_store_reviews' => $totalStoreReviews,
                'total_product_reviews' => $totalProductReviews,
                'average_store_rating' => $avgStoreRating,
                'average_product_rating' => $avgProductRating,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * List product reviews with filters and pagination
     */
    public function listProductReviews(Request $request)
    {
        try {
            $query = ProductReview::with(['user', 'orderItem.product.store']);

            if ($request->filled('rating')) {
                $query->where('rating', (int) $request->rating);
            }

            if ($request->filled('product_id')) {
                $query->whereHas('orderItem.product', function ($q) use ($request) {
                    $q->where('id', (int) $request->product_id);
                });
            }

            if ($request->filled('store_id')) {
                $query->whereHas('orderItem.product.store', function ($q) use ($request) {
                    $q->where('id', (int) $request->store_id);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('full_name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      })
                      ->orWhereHas('orderItem.product', function ($pq) use ($search) {
                          $pq->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('orderItem.product.store', function ($sq) use ($search) {
                          $sq->where('store_name', 'like', "%{$search}%");
                      });
                });
            }

            $reviews = $query->latest()->paginate($request->get('per_page', 20));

            return ResponseHelper::success([
                'reviews' => $reviews->map(function (ProductReview $review) {
                    $product = $review->orderItem?->product;
                    $store = $product?->store;
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'images' => $review->images,
                        'user' => $review->user ? [
                            'id' => $review->user->id,
                            'full_name' => $review->user->full_name,
                            'email' => $review->user->email,
                        ] : null,
                        'product' => $product ? [
                            'id' => $product->id,
                            'name' => $product->name,
                        ] : null,
                        'store' => $store ? [
                            'id' => $store->id,
                            'store_name' => $store->store_name,
                        ] : null,
                        'created_at' => $review->created_at,
                        'formatted_date' => $review->created_at->format('d-m-Y H:i A'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Product review details
     */
    public function productReviewDetails($reviewId)
    {
        try {
            $review = ProductReview::with(['user', 'orderItem.product.store'])->findOrFail($reviewId);
            $product = $review->orderItem?->product;
            $store = $product?->store;

            return ResponseHelper::success([
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'images' => $review->images,
                'user' => $review->user ? [
                    'id' => $review->user->id,
                    'full_name' => $review->user->full_name,
                    'email' => $review->user->email,
                ] : null,
                'product' => $product ? [
                    'id' => $product->id,
                    'name' => $product->name,
                ] : null,
                'store' => $store ? [
                    'id' => $store->id,
                    'store_name' => $store->store_name,
                ] : null,
                'created_at' => $review->created_at,
                'formatted_date' => $review->created_at->format('d-m-Y H:i A'),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * List store reviews with filters and pagination
     */
    public function listStoreReviews(Request $request)
    {
        try {
            $query = StoreReview::with(['user', 'store']);

            if ($request->filled('rating')) {
                $query->where('rating', (int) $request->rating);
            }

            if ($request->filled('store_id')) {
                $query->where('store_id', (int) $request->store_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('full_name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      })
                      ->orWhereHas('store', function ($sq) use ($search) {
                          $sq->where('store_name', 'like', "%{$search}%");
                      });
                });
            }

            $reviews = $query->latest()->paginate($request->get('per_page', 20));

            return ResponseHelper::success([
                'reviews' => $reviews->map(function (StoreReview $review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'images' => $review->images,
                        'user' => $review->user ? [
                            'id' => $review->user->id,
                            'full_name' => $review->user->full_name,
                            'email' => $review->user->email,
                        ] : null,
                        'store' => $review->store ? [
                            'id' => $review->store->id,
                            'store_name' => $review->store->store_name,
                        ] : null,
                        'created_at' => $review->created_at,
                        'formatted_date' => $review->created_at->format('d-m-Y H:i A'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Store review details
     */
    public function storeReviewDetails($reviewId)
    {
        try {
            $review = StoreReview::with(['user', 'store'])->findOrFail($reviewId);

            return ResponseHelper::success([
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'images' => $review->images,
                'user' => $review->user ? [
                    'id' => $review->user->id,
                    'full_name' => $review->user->full_name,
                    'email' => $review->user->email,
                ] : null,
                'store' => $review->store ? [
                    'id' => $review->store->id,
                    'store_name' => $review->store->store_name,
                ] : null,
                'created_at' => $review->created_at,
                'formatted_date' => $review->created_at->format('d-m-Y H:i A'),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete a review (product or store)
     */
    public function deleteProductReview($reviewId)
    {
        try {
            $review = ProductReview::findOrFail($reviewId);
            $review->delete();
            return ResponseHelper::success(null, 'Product review deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function deleteStoreReview($reviewId)
    {
        try {
            $review = StoreReview::findOrFail($reviewId);
            $review->delete();
            return ResponseHelper::success(null, 'Store review deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}


