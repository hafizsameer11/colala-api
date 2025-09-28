<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoreReviewController extends Controller
{
    public function index($storeId)
    {
        $store = Store::findOrFail($storeId);

        $reviews = $store->storeReviews()
            ->with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $reviews
        ]);
    }

    public function store(Request $request, $storeId)
    {
        $store = Store::findOrFail($storeId);

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'images'  => 'nullable|array',
            'images.*'=> 'image|max:2048'
        ]);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('store_reviews', 'public');
            }
        }

        $review = StoreReview::create([
            'store_id' => $store->id,
            'user_id'  => Auth::id(),
            'rating'   => $validated['rating'],
            'comment'  => $validated['comment'] ?? null,
            'images'   => $imagePaths,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Review added successfully.',
            'data'    => $review->load('user:id,name,email')
        ], 201);
    }

    public function update(Request $request, $storeId, $reviewId)
    {
        $review = StoreReview::where('store_id', $storeId)->findOrFail($reviewId);

        if ($review->user_id !== Auth::id()) {
            return response()->json(['status'=>'error','message'=>'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rating'  => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'images'  => 'nullable|array',
            'images.*'=> 'image|max:2048'
        ]);

        if ($request->hasFile('images')) {
            if (is_array($review->images)) {
                foreach ($review->images as $img) {
                    Storage::disk('public')->delete($img);
                }
            }
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('store_reviews', 'public');
            }
            $validated['images'] = $imagePaths;
        }

        $review->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Review updated successfully.',
            'data'    => $review->load('user:id,name,email')
        ]);
    }

    public function destroy($storeId, $reviewId)
    {
        $review = StoreReview::where('store_id', $storeId)->findOrFail($reviewId);

        if ($review->user_id !== Auth::id()) {
            return response()->json(['status'=>'error','message'=>'Unauthorized'], 403);
        }

        if (is_array($review->images)) {
            foreach ($review->images as $img) {
                Storage::disk('public')->delete($img);
            }
        }

        $review->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Review deleted successfully.'
        ]);
    }
}
