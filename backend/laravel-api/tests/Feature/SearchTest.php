<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_search_returns_results_for_valid_query(): void
    {
        // Mock embedding service to return a vector similar to what posts have
        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('generateEmbedding')
                 ->andReturn(array_fill(0, 384, 0.1));
        });

        // Create posts with embeddings (same as the mock will produce for the query)
        Post::factory()->count(3)->create([
            'embedding' => array_fill(0, 384, 0.1),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/search?q=interesting topic');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['count', 'threshold'],
            ])
            ->assertJsonPath('meta.threshold', 0.2);

        // Since query embedding matches post embeddings exactly, should return results
        $this->assertGreaterThan(0, $response->json('meta.count'));
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        // Mock returns a very different vector from what posts have
        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('generateEmbedding')
                 ->andReturn(array_fill(0, 384, 0.9));
        });

        // Create posts with orthogonal embeddings
        $embedding = array_fill(0, 384, 0.0);
        $embedding[0] = 1.0; // only first dimension non-zero
        Post::factory()->count(3)->create([
            'embedding' => $embedding,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/search?q=something completely unrelated');

        $response->assertStatus(200)
            ->assertJsonPath('meta.count', 0);
    }

    public function test_search_requires_query_parameter(): void
    {
        $this->mock(EmbeddingService::class);

        $response = $this->actingAs($this->user)
            ->getJson('/api/search');

        $response->assertStatus(422);
    }
}
