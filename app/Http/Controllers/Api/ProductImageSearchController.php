<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Google\Cloud\Vision\V1\Client\ProductSearchClient;
use Illuminate\Http\Request;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\ProductSearchParams;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductImageSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $path = $request->file('image')->store('temp/vision', 'public');
        $fullPath = storage_path('app/public/' . $path);
        $client = null;

        try {
            $projectId = config('services.google.project_id');
            $location = config('services.google.location', 'us-west1');

            $client = new ProductSearchClient([
                'credentials' => base_path(env('GOOGLE_APPLICATION_CREDENTIALS')),
                'apiEndpoint' => sprintf('%s-vision.googleapis.com', $location)
            ]);

            $productSetId = 'fashion-set';
            $productSetPath = $client->productSetName($projectId, $location, $productSetId);

            $imageObject = new Image();
            $imageObject->setContent(file_get_contents($fullPath));

            $searchParams = new ProductSearchParams();
            $searchParams->setProductSet($productSetPath);
            $searchParams->setProductCategories(['apparel-v2']);

            $parent = sprintf('projects/%s/locations/%s', $projectId, $location);
            $results = $client->searchProducts($parent, $imageObject, $searchParams);

            $matches = [];
            foreach ($results->getResults() as $result) {
                $googleName = $result->getProduct()->getName();
                $localId = (int) str_replace('product-', '', basename($googleName));
                
                if ($local = Product::with(['images', 'store', 'category'])->find($localId)) {
                    $score = $result->getScore();
                    // Optional: apply score threshold (e.g., >= 0.65)
                    if ($score >= 0.65) {
                        $matches[] = [
                            'score' => $score,
                            'product' => $local,
                        ];
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'matches' => $matches,
            ]);

        } finally {
            // Clean up temp file
            Storage::disk('public')->delete($path);
            
            // Close client
            if ($client) {
                $client->close();
            }
        }
    }
}
