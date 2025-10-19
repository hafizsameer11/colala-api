<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ReplicateEmbeddingService
{
    private string $apiToken;
    private string $baseUrl = 'https://api.replicate.com/v1';

    public function __construct()
    {
        $this->apiToken = env('REPLICATE_API_TOKEN');
        
        if (empty($this->apiToken)) {
            throw new Exception('REPLICATE_API_TOKEN environment variable is not set');
        }
    }

    /**
     * Generate CLIP embedding for an image URL
     *
     * @param string $imageUrl
     * @return array
     * @throws Exception
     */
    public function generateEmbedding(string $imageUrl): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Prefer' => 'wait',
                ])
                ->timeout(120) // 2 minutes timeout
                ->post($this->baseUrl . '/models/openai/clip/predictions', [
                    'input' => [
                        'image' => $imageUrl
                    ]
                ]);

            if (!$response->successful()) {
                throw new Exception("API request failed with status {$response->status()}: {$response->body()}");
            }

            $responseData = $response->json();
            
            if (!isset($responseData['output']) || !is_array($responseData['output'])) {
                throw new Exception('Invalid response format from Replicate API');
            }

            $output = $responseData['output'];
            
            if (!isset($output[0]['embedding']) || !is_array($output[0]['embedding'])) {
                throw new Exception('Embedding not found in API response');
            }

            return $output[0]['embedding'];

        } catch (Exception $e) {
            $this->logError("Failed to generate embedding for image: {$imageUrl}", $e);
            throw $e;
        }
    }

    /**
     * Test the API connection
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->get($this->baseUrl . '/models/openai/clip');

            return $response->successful();
        } catch (Exception $e) {
            $this->logError('API connection test failed', $e);
            return false;
        }
    }

    /**
     * Log error to replicate_embeddings.log
     *
     * @param string $message
     * @param Exception|null $exception
     */
    private function logError(string $message, ?Exception $exception = null): void
    {
        $logMessage = '[' . now()->format('Y-m-d H:i:s') . '] ' . $message;
        
        if ($exception) {
            $logMessage .= ' - error: ' . $exception->getMessage();
        }

        // Write to custom log file
        $logFile = storage_path('logs/replicate_embeddings.log');
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
