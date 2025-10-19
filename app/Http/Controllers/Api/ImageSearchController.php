<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEmbedding;
use App\Services\ReplicateEmbeddingService;
use App\Helpers\Vector;
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
        ]);

        $top = (int)($request->input('top', 10));

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

            // Compare with DB embeddings (chunked)
            $scores = [];
            ProductEmbedding::query()
                ->select(['product_id', 'image_id', 'embedding'])
                ->chunk(2000, function ($rows) use (&$scores, $queryEmbedding) {
                    foreach ($rows as $row) {
                        // Get embedding array (already cast by Eloquent)
                        $emb = $row->embedding;
                        if (!is_array($emb) || empty($emb)) continue;
                        $score = Vector::cosineSimilarity($queryEmbedding, $emb);
                        $scores[] = [
                            'product_id' => $row->product_id,
                            'image_id'   => $row->image_id,
                            'score'      => $score,
                        ];
                    }
                });

            // Sort by score desc and take top N
            usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
            $topScores = array_slice($scores, 0, $top);

            // Load products
            $ids = array_unique(array_column($topScores, 'product_id'));
            $products = Product::with(['images' => function ($q) {
                $q->orderByDesc('is_main')->orderBy('id');
            }])->whereIn('id', $ids)->get()->keyBy('id');

            // Merge results
            $results = [];
            foreach ($topScores as $row) {
                if (isset($products[$row['product_id']])) {
                    $p = $products[$row['product_id']];
                    $mainImage = optional($p->images->first())->path;
                    $mainImageUrl = $mainImage ? (rtrim(config('app.url'), '/') . '/storage/' . ltrim($mainImage, '/')) : null;

                    $results[] = [
                        'score' => round($row['score'], 6),
                        'product' => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'price' => $p->price,
                            'discount_price' => $p->discount_price,
                            'store_id' => $p->store_id,
                            'category_id' => $p->category_id,
                            'average_rating' => $p->average_rating,
                            'image_url' => $mainImageUrl,
                        ],
                    ];
                }
            }

            // Log::channel('replicate')->info("[ImageSearch] Query ok; url={$publicUrl} results=" . count($results));

            return response()->json([
                'status' => 'success',
                'query_image_url' => $publicUrl,
                'count' => count($results),
                'results' => $results,
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
