<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\AuthenticityScoreService;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function __construct(
        private AuthenticityScoreService $authenticityScoreService,
        private EmbeddingService $embeddingService,
    ) {}

    public function store(CreatePostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $authenticityScore = $this->authenticityScoreService->calculate(
            $validated['text'],
            $validated['image_url'] ?? null
        );

        $embedding = $this->embeddingService->generateEmbedding($validated['text']);

        $post = Post::create([
            'user_id' => $request->user()->id,
            'text' => $validated['text'],
            'image_url' => $validated['image_url'] ?? null,
            'authenticity_score' => $authenticityScore,
            'embedding' => $embedding,
        ]);

        $post->load('user');

        return response()->json([
            'data' => new PostResource($post),
            'meta' => [
                'embedding_status' => $embedding ? 'generated' : 'pending',
            ],
        ], 201);
    }
}
