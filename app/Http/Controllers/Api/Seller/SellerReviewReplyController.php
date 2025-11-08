<?php

namespace App\Http\Controllers\Api\Seller;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\StoreReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SellerReviewReplyController extends Controller
{
    /**
     * Reply to a store review
     * POST /api/seller/reviews/store/{reviewId}/reply
     */
    public function replyToStoreReview(Request $request, $reviewId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reply' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            
            // Get user's store
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found. You must be a store owner to reply to reviews.', 403);
            }

            // Find the review and verify it belongs to the seller's store
            $review = StoreReview::where('id', $reviewId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            // Update the review with seller's reply
            $review->update([
                'seller_reply' => $request->reply,
                'seller_replied_at' => now(),
            ]);

            // Load relationships for response
            $review->load(['user', 'store']);

            return ResponseHelper::success([
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'images' => $review->images,
                'seller_reply' => $review->seller_reply,
                'seller_replied_at' => $review->seller_replied_at?->toIso8601String(),
                'user' => [
                    'id' => $review->user->id,
                    'full_name' => $review->user->full_name,
                    'profile_picture' => $review->user->profile_picture ? asset('storage/' . $review->user->profile_picture) : null,
                ],
                'store' => [
                    'id' => $review->store->id,
                    'store_name' => $review->store->store_name,
                ],
                'created_at' => $review->created_at->toIso8601String(),
            ], 'Reply added successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Review not found or you do not have permission to reply to this review', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Reply to a product review
     * POST /api/seller/reviews/product/{reviewId}/reply
     */
    public function replyToProductReview(Request $request, $reviewId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reply' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            
            // Get user's store
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found. You must be a store owner to reply to reviews.', 403);
            }

            // Find the review and verify the product belongs to the seller's store
            $review = ProductReview::with(['orderItem.product'])
                ->where('id', $reviewId)
                ->whereHas('orderItem.product', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->firstOrFail();

            // Update the review with seller's reply
            $review->update([
                'seller_reply' => $request->reply,
                'seller_replied_at' => now(),
            ]);

            // Load relationships for response
            $review->load(['user', 'orderItem.product']);

            return ResponseHelper::success([
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'images' => $review->images,
                'seller_reply' => $review->seller_reply,
                'seller_replied_at' => $review->seller_replied_at?->toIso8601String(),
                'user' => [
                    'id' => $review->user->id,
                    'full_name' => $review->user->full_name,
                    'profile_picture' => $review->user->profile_picture ? asset('storage/' . $review->user->profile_picture) : null,
                ],
                'product' => [
                    'id' => $review->orderItem->product->id,
                    'name' => $review->orderItem->product->name,
                ],
                'created_at' => $review->created_at->toIso8601String(),
            ], 'Reply added successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Review not found or you do not have permission to reply to this review', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update a store review reply
     * PUT /api/seller/reviews/store/{reviewId}/reply
     */
    public function updateStoreReviewReply(Request $request, $reviewId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reply' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 403);
            }

            $review = StoreReview::where('id', $reviewId)
                ->where('store_id', $store->id)
                ->whereNotNull('seller_reply')
                ->firstOrFail();

            $review->update([
                'seller_reply' => $request->reply,
            ]);

            $review->load(['user', 'store']);

            return ResponseHelper::success([
                'id' => $review->id,
                'seller_reply' => $review->seller_reply,
                'seller_replied_at' => $review->seller_replied_at?->toIso8601String(),
            ], 'Reply updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Review not found or reply does not exist', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update a product review reply
     * PUT /api/seller/reviews/product/{reviewId}/reply
     */
    public function updateProductReviewReply(Request $request, $reviewId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reply' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 403);
            }

            $review = ProductReview::where('id', $reviewId)
                ->whereHas('orderItem.product', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->whereNotNull('seller_reply')
                ->firstOrFail();

            $review->update([
                'seller_reply' => $request->reply,
            ]);

            return ResponseHelper::success([
                'id' => $review->id,
                'seller_reply' => $review->seller_reply,
                'seller_replied_at' => $review->seller_replied_at?->toIso8601String(),
            ], 'Reply updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Review not found or reply does not exist', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete a store review reply
     * DELETE /api/seller/reviews/store/{reviewId}/reply
     */
    public function deleteStoreReviewReply($reviewId)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 403);
            }

            $review = StoreReview::where('id', $reviewId)
                ->where('store_id', $store->id)
                ->whereNotNull('seller_reply')
                ->firstOrFail();

            $review->update([
                'seller_reply' => null,
                'seller_replied_at' => null,
            ]);

            return ResponseHelper::success(null, 'Reply deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Review not found or reply does not exist', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete a product review reply
     * DELETE /api/seller/reviews/product/{reviewId}/reply
     */
    public function deleteProductReviewReply($reviewId)
    {
        try {
            $user = Auth::user();
            $store = Store::where('user_id', $user->id)->first();
            
            if (!$store) {
                return ResponseHelper::error('Store not found', 403);
            }

            $review = ProductReview::where('id', $reviewId)
                ->whereHas('orderItem.product', function ($query) use ($store) {
                    $query->where('store_id', $store->id);
                })
                ->whereNotNull('seller_reply')
                ->firstOrFail();

            $review->update([
                'seller_reply' => null,
                'seller_replied_at' => null,
            ]);

            return ResponseHelper::success(null, 'Reply deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Review not found or reply does not exist', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
