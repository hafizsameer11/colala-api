<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CameraSearchController extends Controller
{
    public function searchByImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
                'type' => 'required|in:product,store,service',
            ]);

            $image = $request->file('image');
            $type = $request->input('type');
            
            // Store the image temporarily
            $imagePath = $image->store('temp/camera-search', 'public');
            $fullImagePath = storage_path('app/public/' . $imagePath);

            // Extract text from image using OCR
            $extractedText = $this->extractTextFromImage($fullImagePath);
            
            // Clean up temporary file
            Storage::disk('public')->delete($imagePath);

            if (empty($extractedText)) {
                return ResponseHelper::error('No text found in the image. Please try a clearer image.', 400);
            }

            // Search based on extracted text
            $results = $this->performSearch($extractedText, $type);

            return ResponseHelper::success([
                'extracted_text' => $extractedText,
                'search_results' => $results,
                'search_query' => $extractedText
            ], 'Camera search completed successfully');

        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function searchByBarcode(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
                'type' => 'required|in:product,store,service',
            ]);

            $image = $request->file('image');
            $type = $request->input('type');
            
            // Store the image temporarily
            $imagePath = $image->store('temp/barcode-search', 'public');
            $fullImagePath = storage_path('app/public/' . $imagePath);

            // Extract barcode from image
            $barcode = $this->extractBarcodeFromImage($fullImagePath);
            
            // Clean up temporary file
            Storage::disk('public')->delete($imagePath);

            if (empty($barcode)) {
                return ResponseHelper::error('No barcode found in the image. Please try a clearer image.', 400);
            }

            // Search products by barcode
            $results = $this->searchByBarcodeNumber($barcode, $type);

            return ResponseHelper::success([
                'barcode' => $barcode,
                'search_results' => $results,
            ], 'Barcode search completed successfully');

        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    private function extractTextFromImage($imagePath)
    {
        try {
            // Option 1: Using Google Vision API (recommended for production)
            if (config('services.google.vision_api_key')) {
                return $this->extractTextUsingGoogleVision($imagePath);
            }

            // Option 2: Using Tesseract OCR (requires tesseract-ocr installed)
            return $this->extractTextUsingTesseract($imagePath);

        } catch (\Exception $e) {
            throw new \Exception('Text extraction failed: ' . $e->getMessage());
        }
    }

    private function extractTextUsingGoogleVision($imagePath)
    {
        $apiKey = config('services.google.vision_api_key');
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
            'requests' => [
                [
                    'image' => [
                        'content' => base64_encode(file_get_contents($imagePath))
                    ],
                    'features' => [
                        [
                            'type' => 'TEXT_DETECTION',
                            'maxResults' => 10
                        ]
                    ]
                ]
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $texts = [];
            
            if (isset($data['responses'][0]['textAnnotations'])) {
                foreach ($data['responses'][0]['textAnnotations'] as $annotation) {
                    $texts[] = $annotation['description'];
                }
            }
            
            return implode(' ', $texts);
        }

        throw new \Exception('Google Vision API request failed');
    }

    private function extractTextUsingTesseract($imagePath)
    {
        // This requires tesseract-ocr to be installed on the server
        $command = "tesseract " . escapeshellarg($imagePath) . " stdout";
        $output = shell_exec($command);
        
        return trim($output ?? '');
    }

    private function extractBarcodeFromImage($imagePath)
    {
        try {
            // Using ZXing library via command line (requires zxing installed)
            $command = "zbarimg " . escapeshellarg($imagePath) . " 2>/dev/null";
            $output = shell_exec($command);
            
            if ($output) {
                // Extract barcode number from output
                preg_match('/:([0-9]+)/', $output, $matches);
                return $matches[1] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function performSearch($searchText, $type)
    {
        $searchTerms = $this->extractSearchTerms($searchText);
        
        switch ($type) {
            case 'product':
                return Product::with(['store', 'category:id,title'])
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

    private function searchByBarcodeNumber($barcode, $type)
    {
        // Search products by barcode (assuming you have a barcode field)
        if ($type === 'product') {
            return Product::with(['store', 'category:id,title'])
                ->where('barcode', $barcode)
                ->orWhere('sku', $barcode)
                ->paginate(20);
        }

        return collect([]);
    }

    private function extractSearchTerms($text)
    {
        // Clean and extract meaningful search terms
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $words = array_filter(explode(' ', strtolower($text)), function($word) {
            return strlen($word) > 2; // Only words longer than 2 characters
        });
        
        return array_unique($words);
    }
}
