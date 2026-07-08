<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.embedding.url', 'http://localhost:8001');
    }

    /**
     * Generate an embedding vector for the given text.
     *
     * Makes a POST request to the Python embedding service and returns
     * the 384-dimensional float array, or null on any failure.
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            $response = Http::timeout(5)
                ->post("{$this->baseUrl}/embed", [
                    'text' => $text,
                ]);

            if ($response->successful()) {
                return $response->json('embedding');
            }

            Log::warning('Embedding service returned non-200 response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('Embedding service request failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
