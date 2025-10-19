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
            $this->logInfo("Starting embedding generation for image: {$imageUrl}");
            
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

            $this->logInfo("API Response Status: {$response->status()}");
            $this->logInfo("API Response Headers: " . json_encode($response->headers()));

            if (!$response->successful()) {
                $this->logError("API request failed with status {$response->status()}", null, [
                    'response_body' => $response->body(),
                    'response_headers' => $response->headers(),
                    'image_url' => $imageUrl
                ]);
                throw new Exception("API request failed with status {$response->status()}: {$response->body()}");
            }

            $responseData = $response->json();
            $this->logInfo("API Response Data: " . json_encode($responseData));
            
            if (!isset($responseData['output']) || !is_array($responseData['output'])) {
                $this->logError('Invalid response format from Replicate API', null, [
                    'response_data' => $responseData,
                    'image_url' => $imageUrl
                ]);
                throw new Exception('Invalid response format from Replicate API');
            }

            $output = $responseData['output'];
            $this->logInfo("Output data: " . json_encode($output));
            
            if (!isset($output[0]['embedding']) || !is_array($output[0]['embedding'])) {
                $this->logError('Embedding not found in API response', null, [
                    'output_data' => $output,
                    'image_url' => $imageUrl
                ]);
                throw new Exception('Embedding not found in API response');
            }

            $embedding = $output[0]['embedding'];
            $this->logInfo("Successfully generated embedding with " . count($embedding) . " dimensions for image: {$imageUrl}");

            return $embedding;

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
            $this->logInfo("Testing API connection to: {$this->baseUrl}/models/openai/clip");
            
            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->get($this->baseUrl . '/models/openai/clip');

            $this->logInfo("Connection test response status: {$response->status()}");
            $this->logInfo("Connection test response: " . $response->body());

            return $response->successful();
        } catch (Exception $e) {
            $this->logError('API connection test failed', $e);
            return false;
        }
    }

    /**
     * Log info message to replicate_embeddings.log
     *
     * @param string $message
     */
    private function logInfo(string $message): void
    {
        $logMessage = '[' . now()->format('Y-m-d H:i:s') . '] [INFO] ' . $message;
        $this->writeToLog($logMessage);
    }

    /**
     * Log error to replicate_embeddings.log
     *
     * @param string $message
     * @param Exception|null $exception
     * @param array|null $context
     */
    private function logError(string $message, ?Exception $exception = null, ?array $context = null): void
    {
        $logMessage = '[' . now()->format('Y-m-d H:i:s') . '] [ERROR] ' . $message;
        
        if ($exception) {
            $logMessage .= ' - error: ' . $exception->getMessage();
        }

        if ($context) {
            $logMessage .= ' - context: ' . json_encode($context);
        }

        $this->writeToLog($logMessage);
    }

    /**
     * Write message to log file
     *
     * @param string $message
     */
    private function writeToLog(string $message): void
    {
        $logFile = storage_path('logs/replicate_embeddings.log');
        file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
