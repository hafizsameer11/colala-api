<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEmbedding;
use App\Services\ReplicateEmbeddingService;
use App\Helpers\Vector;
use App\Helpers\ProductStatHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB
            'top'   => 'sometimes|integer|min:1|max:50',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'brand' => 'sometimes|string|max:255',
            'threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        $top = (int)($request->input('top', 10));
        $categoryId = $request->input('category_id');
        $brand = $request->input('brand');
        $threshold = (float)($request->input('threshold', 0.8));

        // Save uploaded file to public disk
        $path = $request->file('image')->store('temp/search', 'public');
        $publicUrl = rtrim(config('app.url'), '/') . '/storage/' . $path;

        try {
            $embedService = app(ReplicateEmbeddingService::class);
            $queryEmbedding = $embedService->embeddingFromUrl($publicUrl);

            if (!$queryEmbedding || !is_array($queryEmbedding) || count($queryEmbedding) < 32) {
                Log::channel('replicate')->error("[ImageSearch] Invalid embedding for query image: {$publicUrl}");
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to generate a valid embedding for the image.',
                ], 500);
            }

            // Normalize the query embedding
            $queryEmbedding = Vector::normalize($queryEmbedding);

            // Compare with DB embeddings (chunked)
            $scores = [];
            $query = ProductEmbedding::query()
                ->select(['product_id', 'image_id', 'embedding'])
                ->with(['product' => function ($q) use ($categoryId, $brand) {
                    if ($categoryId) {
                        $q->where('category_id', $categoryId);
                    }
                    if ($brand) {
                        $q->where('brand', 'like', '%' . $brand . '%');
                    }
                }]);

            $query->chunk(2000, function ($rows) use (&$scores, $queryEmbedding, $threshold) {
                foreach ($rows as $row) {
                    // Get embedding array (already cast by Eloquent)
                    $emb = $row->embedding;
                    if (!is_array($emb) || empty($emb)) continue;
                    
                    // Normalize the stored embedding
                    $normalizedEmb = Vector::normalize($emb);
                    
                    // Calculate similarity using normalized vectors
                    $score = Vector::cosineSimilarity($queryEmbedding, $normalizedEmb);
                    
                    // Apply similarity threshold
                    if ($score >= $threshold) {
                        $scores[] = [
                            'product_id' => $row->product_id,
                            'image_id'   => $row->image_id,
                            'score'      => $score,
                        ];
                    }
                }
            });

            // Sort by score desc and take top N
            usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
            $topScores = array_slice($scores, 0, $top);

            // Load products with the same relationships as CameraSearchController
            $ids = array_unique(array_column($topScores, 'product_id'));
            $productQuery = Product::with(['store', 'category:id,title', 'images'])
                ->whereIn('id', $ids);
            
            // Apply additional filters to the product query
            if ($categoryId) {
                $productQuery->where('category_id', $categoryId);
            }
            if ($brand) {
                $productQuery->where('brand', 'like', '%' . $brand . '%');
            }
            
            $products = $productQuery->get()->keyBy('id');

            // Merge results
            $results = [];
            foreach ($topScores as $row) {
                if (isset($products[$row['product_id']])) {
                    $results[] = [
                        'score' => round($row['score'], 4), // Round to 4 decimals
                        'product' => $products[$row['product_id']],
                    ];
                }
            }

            Log::channel('replicate')->info("[ImageSearch] Query ok; url={$publicUrl} results=" . count($results) . " threshold={$threshold}");

            // Record impressions for products (same as CameraSearchController)
            if (count($results) > 0) {
                foreach ($results as $result) {
                    ProductStatHelper::record($result['product']->id, 'impression');
                }
            }

            // Format results to match CameraSearchController format exactly
            $formattedResults = collect($results)->map(function ($item) {
                $product = $item['product'];
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount_price' => $product->discount_price,
                    'brand' => $product->brand,
                    'sku' => $product->sku,
                    'stock_quantity' => $product->stock_quantity,
                    'average_rating' => $product->average_rating,
                    'store_id' => $product->store_id,
                    'category_id' => $product->category_id,
                    'status' => $product->status,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'store' => $product->store,
                    'category' => $product->category,
                    'images' => $product->images,
                    'similarity_score' => $item['score'],
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Image search completed successfully.',
                'detected_terms' => ['visual_similarity'],
                'search_results' => [
                    'current_page' => 1,
                    'data' => $formattedResults,
                    'first_page_url' => url('/api/image-search?page=1'),
                    'from' => 1,
                    'last_page' => 1,
                    'last_page_url' => url('/api/image-search?page=1'),
                    'links' => [
                        [
                            'url' => null,
                            'label' => '&laquo; Previous',
                            'active' => false,
                        ],
                        [
                            'url' => url('/api/image-search?page=1'),
                            'label' => '1',
                            'active' => true,
                        ],
                        [
                            'url' => null,
                            'label' => 'Next &raquo;',
                            'active' => false,
                        ],
                    ],
                    'next_page_url' => null,
                    'path' => url('/api/image-search'),
                    'per_page' => count($formattedResults),
                    'prev_page_url' => null,
                    'to' => count($formattedResults),
                    'total' => count($formattedResults),
                ],
                'search_query' => 'Visual similarity search',
                'metadata' => [
                    'query_image_url' => $publicUrl,
                    'count' => count($results),
                    'threshold' => $threshold,
                    'filters' => [
                        'category_id' => $categoryId,
                        'brand' => $brand,
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            Log::channel('replicate')->error("[ImageSearch] Failure: {$e->getMessage()}");
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        } finally {
            // cleanup temp
            Storage::disk('public')->delete($path);
        }
    }
}
