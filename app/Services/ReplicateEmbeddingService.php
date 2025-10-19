<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\Vector;
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
            
            // Check if embedding is directly in output
            if (isset($output['embedding']) && is_array($output['embedding'])) {
                $embedding = $output['embedding'];
            }
            // Check if embedding is in output[0] (array format)
            elseif (isset($output[0]['embedding']) && is_array($output[0]['embedding'])) {
                $embedding = $output[0]['embedding'];
            }
            else {
                $this->logError('Embedding not found in API response', null, [
                    'output_data' => $output,
                    'image_url' => $imageUrl
                ]);
                throw new Exception('Embedding not found in API response');
            }
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

    /**
     * Generate embedding from URL for image search
     *
     * @param string $imageUrl
     * @return array|null
     * @throws \RuntimeException
     */
    public function embeddingFromUrl(string $imageUrl): ?array
    {
        $token = env('REPLICATE_API_TOKEN');
        if (!$token) {
            Log::channel('replicate')->error("[Replicate] Missing REPLICATE_API_TOKEN");
            throw new \RuntimeException('Replicate token missing');
        }

        $this->logInfo("Starting embedding generation for image search: {$imageUrl}");

        $resp = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Prefer'       => 'wait',
            ])
            ->timeout(120)
            ->post($this->baseUrl . '/models/openai/clip/predictions', [
                'input' => [
                    'image' => $imageUrl,
                ],
            ]);

        $this->logInfo("API Response Status: {$resp->status()}");
        $this->logInfo("API Response Headers: " . json_encode($resp->headers()));

        if ($resp->status() === 402) {
            Log::channel('replicate')->error("[Replicate] 402 Insufficient credit");
            throw new \RuntimeException('Replicate: insufficient credit (402)');
        }

        if (!$resp->successful()) {
            $this->logError("API request failed with status {$resp->status()}", null, [
                'response_body' => $resp->body(),
                'response_headers' => $resp->headers(),
                'image_url' => $imageUrl
            ]);
            throw new \RuntimeException("Replicate error {$resp->status()}");
        }

        $json = $resp->json();
        $this->logInfo("API Response Data: " . json_encode($json));

        // Expected: output[0].embedding or output.embedding depending on model shape
        $output = $json['output'] ?? null;

        // Handle common shapes:
        if (is_array($output) && isset($output[0]['embedding']) && is_array($output[0]['embedding'])) {
            $embedding = $output[0]['embedding'];
        } elseif (is_array($output) && isset($output['embedding']) && is_array($output['embedding'])) {
            $embedding = $output['embedding'];
        } else {
            $this->logError('Invalid response format from Replicate API', null, [
                'response_data' => $json,
                'image_url' => $imageUrl
            ]);
            throw new \RuntimeException('Invalid response format from Replicate API');
        }

        $this->logInfo("Successfully generated embedding with " . count($embedding) . " dimensions for image search: {$imageUrl}");

        // Normalize the embedding before returning
        return Vector::normalize($embedding);
    }
}
