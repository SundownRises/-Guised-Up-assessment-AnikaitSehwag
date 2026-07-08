<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string',
        ]);

        $embedding = $this->embeddingService->generateEmbedding($request->query('q'));

        if ($embedding === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'count' => 0,
                    'threshold' => 0.2,
                    'message' => 'Embedding service unavailable',
                ],
            ]);
        }

        $embeddingString = '[' . implode(',', $embedding) . ']';

        $results = DB::select("
            SELECT posts.*, (1 - (embedding <=> ?::vector)) as similarity_score
            FROM posts
            WHERE embedding IS NOT NULL
            AND (1 - (embedding <=> ?::vector)) >= 0.2
            ORDER BY similarity_score DESC
            LIMIT 10
        ", [$embeddingString, $embeddingString]);

        $posts = Post::hydrate($results);
        $posts->load('user');

        $data = $posts->map(function ($post) {
            $resource = new PostResource($post);
            return array_merge($resource->toArray(request()), [
                'similarity_score' => round((float) $post->similarity_score, 4),
            ]);
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'count' => count($results),
                'threshold' => 0.2,
            ],
        ]);
    }
}
