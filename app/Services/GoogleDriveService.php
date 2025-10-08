<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class GoogleDriveService
{
    /**
     * Download file from Google Drive link
     */
    public function downloadFile(string $googleDriveUrl, string $destinationPath): string
    {
        try {
            // Extract file ID from Google Drive URL
            $fileId = $this->extractFileId($googleDriveUrl);
            
            if (!$fileId) {
                throw new Exception('Invalid Google Drive URL');
            }

            // Get file info first
            $fileInfo = $this->getFileInfo($fileId);
            
            // Download the file
            $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
            $response = Http::timeout(60)->get($downloadUrl);
            
            if (!$response->successful()) {
                throw new Exception('Failed to download file from Google Drive');
            }

            // Generate unique filename
            $extension = $this->getFileExtension($fileInfo['name'] ?? 'file');
            $filename = Str::random(40) . '.' . $extension;
            $fullPath = $destinationPath . '/' . $filename;

            // Store the file
            Storage::disk('public')->put($fullPath, $response->body());

            return $fullPath;
        } catch (Exception $e) {
            throw new Exception("Google Drive download failed: " . $e->getMessage());
        }
    }

    /**
     * Extract file ID from Google Drive URL
     */
    private function extractFileId(string $url): ?string
    {
        // Handle different Google Drive URL formats
        $patterns = [
            '/\/file\/d\/([a-zA-Z0-9_-]+)/',
            '/id=([a-zA-Z0-9_-]+)/',
            '/\/d\/([a-zA-Z0-9_-]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Get file information from Google Drive
     */
    private function getFileInfo(string $fileId): array
    {
        try {
            $response = Http::get("https://drive.google.com/file/d/{$fileId}/view");
            
            if ($response->successful()) {
                // Extract filename from HTML content
                if (preg_match('/<title>([^<]+)/', $response->body(), $matches)) {
                    return ['name' => trim($matches[1])];
                }
            }
        } catch (Exception $e) {
            // Continue with default if info extraction fails
        }

        return ['name' => 'downloaded_file'];
    }

    /**
     * Get file extension from filename
     */
    private function getFileExtension(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return $extension ?: 'jpg'; // Default to jpg for images
    }

    /**
     * Validate Google Drive URL
     */
    public function isValidGoogleDriveUrl(string $url): bool
    {
        return $this->extractFileId($url) !== null;
    }
}
