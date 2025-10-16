<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ProductStatHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CameraSearchController extends Controller
{
    public function searchByImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
                'type' => 'required|in:product,store,service',
            ]);
            Log::info('Camera search started. with request data: ' . json_encode($request->all()));
            $image = $request->file('image');
            $type = $request->input('type');
            
            // Store temporarily
            $imagePath = $image->store('temp/camera-search', 'public');
            $fullImagePath = storage_path('app/public/' . $imagePath);

            // 🔍 Extract text + labels + objects
            $extractedTerms = $this->extractVisualData($fullImagePath);
            Storage::disk('public')->delete($imagePath);

            if (empty($extractedTerms)) {
                return ResponseHelper::error('No recognizable content found in the image. Try a clearer or more detailed image.', 400);
            }

            // 🔎 Perform DB search
            $results = $this->performSearch($extractedTerms, $type);

            // Record impressions
            if ($type === 'product' && $results->count() > 0) {
                foreach ($results as $product) {
                    ProductStatHelper::record($product->id, 'impression');
                }
            }

            return ResponseHelper::success([
                'detected_terms' => $extractedTerms,
                'search_results' => $results,
                'search_query' => implode(', ', $extractedTerms),
            ], 'Camera search completed successfully.');

        } catch (\Exception $e) {
            Log::error("Camera search error: " . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Extracts text, labels, and objects from image using Google Vision.
     */
    private function extractVisualData($imagePath)
    {
        try {
            if (config('services.google.vision_api_key')) {
                $apiKey = config('services.google.vision_api_key');
                $base64Image = base64_encode(file_get_contents($imagePath));

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
                    'requests' => [
                        [
                            'image' => ['content' => $base64Image],
                            'features' => [
                                ['type' => 'TEXT_DETECTION', 'maxResults' => 5],
                                ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10],
                            ],
                        ]
                    ],
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Google Vision API request failed: ' . $response->body());
                }

                $data = $response->json();
                Log::info('Google Vision response: ', $data);

                $terms = [];

                // 🧠 Extract Text
                if (!empty($data['responses'][0]['textAnnotations'])) {
                    foreach ($data['responses'][0]['textAnnotations'] as $annotation) {
                        $terms[] = $annotation['description'];
                    }
                }

                // 🏷️ Extract Labels
                if (!empty($data['responses'][0]['labelAnnotations'])) {
                    foreach ($data['responses'][0]['labelAnnotations'] as $label) {
                        $terms[] = $label['description'];
                    }
                }

                // 📦 Extract Object Names
                if (!empty($data['responses'][0]['localizedObjectAnnotations'])) {
                    foreach ($data['responses'][0]['localizedObjectAnnotations'] as $object) {
                        $terms[] = $object['name'];
                    }
                }

                // Clean & deduplicate
                $terms = $this->extractSearchTerms(implode(' ', $terms));

                return $terms;
            }

            // Fallback if no Vision key: return empty
            return [];

        } catch (\Exception $e) {
            Log::error('Visual extraction failed: ' . $e->getMessage());
            return [];
        }
    }

    private function performSearch($searchTerms, $type)
    {
        switch ($type) {
            case 'product':
                return Product::with(['store', 'category:id,title','images'])
                    ->where(function($query) use ($searchTerms) {
                        foreach ($searchTerms as $term) {
                            $query->orWhere('name', 'LIKE', "%$term%")
                                  ->orWhere('description', 'LIKE', "%$term%")
                                  ->orWhere('brand', 'LIKE', "%$term%");
                        }
                    })
                    ->paginate(20);

            case 'store':
                return Store::with('categories:id,title')
                    ->where(function($query) use ($searchTerms) {
                        foreach ($searchTerms as $term) {
                            $query->orWhere('store_name', 'LIKE', "%$term%")
                                  ->orWhere('store_email', 'LIKE', "%$term%");
                        }
                    })
                    ->paginate(20);

            case 'service':
                return Service::where(function($query) use ($searchTerms) {
                        foreach ($searchTerms as $term) {
                            $query->orWhere('name', 'LIKE', "%$term%")
                                  ->orWhere('full_description', 'LIKE', "%$term%");
                        }
                    })
                    ->paginate(20);
        }
    }

    private function extractSearchTerms($text)
    {
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $words = array_filter(explode(' ', strtolower($text)), fn($w) => strlen($w) > 2);
        return array_unique($words);
    }
}
