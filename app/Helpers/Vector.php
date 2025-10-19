<?php

namespace App\Helpers;

class Vector
{
    /**
     * Normalize a vector to unit length (L2 normalization)
     *
     * @param array $vector Input vector
     * @return array Normalized vector
     */
    public static function normalize(array $vector): array
    {
        $norm = self::magnitude($vector);
        if ($norm == 0.0) {
            return $vector; // Return original if zero vector
        }
        
        $normalized = [];
        foreach ($vector as $component) {
            $normalized[] = (float)$component / $norm;
        }
        
        return $normalized;
    }

    /**
     * Numerically stable cosine similarity with clamping to [-1, 1]
     * Assumes both vectors are already normalized
     *
     * @param array $a First normalized vector
     * @param array $b Second normalized vector
     * @return float Cosine similarity score (-1 to 1)
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }
        
        $dot = 0.0;
        $len = count($a);
        
        for ($i = 0; $i < $len; $i++) {
            $dot += ((float)$a[$i]) * ((float)$b[$i]);
        }
        
        // Clamp to [-1, 1] for numerical stability
        return max(-1.0, min(1.0, $dot));
    }

    /**
     * Calculate cosine similarity for non-normalized vectors
     * (for backward compatibility)
     *
     * @param array $a First vector
     * @param array $b Second vector
     * @return float Cosine similarity score (-1 to 1)
     */
    public static function cosineSimilarityUnnormalized(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));
        
        for ($i = 0; $i < $len; $i++) {
            $ai = (float)$a[$i];
            $bi = (float)$b[$i];
            $dot += $ai * $bi;
            $normA += $ai * $ai;
            $normB += $bi * $bi;
        }
        
        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }
        
        $similarity = $dot / (sqrt($normA) * sqrt($normB));
        return max(-1.0, min(1.0, $similarity));
    }

    /**
     * Calculate Euclidean distance between two vectors
     *
     * @param array $a First vector
     * @param array $b Second vector
     * @return float Euclidean distance
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $len = min(count($a), count($b));
        
        for ($i = 0; $i < $len; $i++) {
            $diff = (float)$a[$i] - (float)$b[$i];
            $sum += $diff * $diff;
        }
        
        return sqrt($sum);
    }

    /**
     * Calculate dot product of two vectors
     *
     * @param array $a First vector
     * @param array $b Second vector
     * @return float Dot product
     */
    public static function dotProduct(array $a, array $b): float
    {
        $dot = 0.0;
        $len = min(count($a), count($b));
        
        for ($i = 0; $i < $len; $i++) {
            $dot += (float)$a[$i] * (float)$b[$i];
        }
        
        return $dot;
    }

    /**
     * Calculate magnitude (norm) of a vector
     *
     * @param array $vector
     * @return float Magnitude
     */
    public static function magnitude(array $vector): float
    {
        $sum = 0.0;
        
        foreach ($vector as $component) {
            $sum += (float)$component * (float)$component;
        }
        
        return sqrt($sum);
    }
}
