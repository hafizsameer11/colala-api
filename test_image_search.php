<?php
/**
 * Test script for Image Search API
 * 
 * Usage: php test_image_search.php
 * 
 * Make sure to:
 * 1. Set REPLICATE_API_TOKEN in .env
 * 2. Set APP_URL to a public domain
 * 3. Have some product embeddings in the database
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Configuration
$baseUrl = 'http://localhost:8000'; // Change to your API URL
$imagePath = 'test_image.jpg'; // Path to test image

// Check if image exists
if (!file_exists($imagePath)) {
    echo "❌ Test image not found: {$imagePath}\n";
    echo "Please provide a test image file.\n";
    exit(1);
}

echo "🔍 Testing Image Search API...\n";
echo "Base URL: {$baseUrl}\n";
echo "Image: {$imagePath}\n\n";

try {
    // Make the API request
    $response = Http::attach('image', file_get_contents($imagePath), basename($imagePath))
        ->post("{$baseUrl}/api/search/by-image", [
            'top' => 5
        ]);

    echo "📡 Response Status: {$response->status()}\n";
    echo "📡 Response Headers: " . json_encode($response->headers()) . "\n\n";

    if ($response->successful()) {
        $data = $response->json();
        
        echo "✅ Success!\n";
        echo "Status: {$data['status']}\n";
        echo "Query Image URL: {$data['query_image_url']}\n";
        echo "Results Count: {$data['count']}\n\n";
        
        if (!empty($data['results'])) {
            echo "🎯 Top Results:\n";
            foreach ($data['results'] as $i => $result) {
                $product = $result['product'];
                echo sprintf(
                    "%d. %s (Score: %.4f)\n   Price: $%.2f\n   Store ID: %d\n   Image: %s\n\n",
                    $i + 1,
                    $product['name'],
                    $result['score'],
                    $product['price'],
                    $product['store_id'],
                    $product['image_url'] ?? 'N/A'
                );
            }
        } else {
            echo "⚠️  No results found. Make sure you have product embeddings in the database.\n";
        }
        
    } else {
        echo "❌ Error!\n";
        echo "Status: {$response->status()}\n";
        echo "Response: " . $response->body() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
}

echo "\n📝 Check logs at: storage/logs/replicate_embeddings.log\n";
